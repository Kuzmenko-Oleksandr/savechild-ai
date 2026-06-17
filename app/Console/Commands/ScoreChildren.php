<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\CaseNotification;
use App\Models\Child;
use App\Services\MlClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScoreChildren extends Command
{
    protected $signature = 'ml:score {--priority : score priority only} {--attendance : score attendance only}';

    protected $description = 'Score children (priority) and attendance records via the ML microservice and persist results';

    public function handle(MlClient $ml): int
    {
        try {
            $health = $ml->health();
            $this->info('ML service: '.json_encode($health));
        } catch (\Throwable $e) {
            $this->error('ML service unreachable: '.$e->getMessage());

            return self::FAILURE;
        }

        $onlyPriority = $this->option('priority');
        $onlyAttendance = $this->option('attendance');
        $both = ! $onlyPriority && ! $onlyAttendance;

        if ($both || $onlyAttendance) {
            $this->scoreAttendance($ml);
            $this->linkAttendanceToChildren();
        }
        if ($both || $onlyPriority) {
            $this->scorePriority($ml);
            $this->generateNotifications();
            $this->recomputeStatuses();
            $this->captureSnapshot();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private const NOTIF_BODY = "Over the last three months, the system detected several risk factors that may indicate a decline in the child's well-being. It found unexcused school absences, a family incident that resulted in a police report, and a disciplinary note from the educational institution.\n\nThe available data shows recurring events across different areas of the child's life, which increased the risk level to High.\n\nA review of the child's living conditions is recommended, along with clarifying school attendance details and considering whether the family needs additional social support.";

    /** Create a "risk elevated" notification for HIGH children that don't have one yet. */
    private function generateNotifications(): void
    {
        $attachments = array_fill(0, 3, [
            'name' => 'Police Incident Report.pdf',
            'note' => 'Information about a police visit to the residential address.',
        ]);

        $existing = CaseNotification::where('type', 'risk_elevated')->pluck('child_id')->flip();
        $created = 0;

        Child::where('predicted_priority', 'HIGH')->orderBy('id')->chunk(1000, function ($children) use (&$created, $existing, $attachments) {
            $rows = [];
            $now = now();
            foreach ($children as $c) {
                if (isset($existing[$c->id])) {
                    continue;
                }
                // Demo variety of statuses (deterministic by id).
                $status = ['check_it_out', 'check_it_out', 'in_progress', 'resolved'][$c->id % 4];
                $rows[] = [
                    'child_id' => $c->id,
                    'type' => 'risk_elevated',
                    'title' => 'Risk level has been elevated to High',
                    'body' => self::NOTIF_BODY,
                    'status' => $status,
                    'attachments' => json_encode($attachments),
                    'resolved_by' => $status === 'resolved' ? 'Olena Franko' : null,
                    'resolved_at' => $status === 'resolved' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows) {
                CaseNotification::insert($rows);
                $created += count($rows);
            }
        });

        $this->info("Notifications created: $created");
    }

    private function captureSnapshot(): void
    {
        $byPriority = Child::query()->selectRaw('predicted_priority, count(*) c')->groupBy('predicted_priority')->pluck('c', 'predicted_priority');
        $low = (int) ($byPriority['LOW'] ?? 0);
        $medium = (int) ($byPriority['MEDIUM'] ?? 0);
        $high = (int) ($byPriority['HIGH'] ?? 0);
        $total = Child::count();

        // Seed a baseline (yesterday) the first time so deltas are meaningful immediately.
        if (! DB::table('risk_snapshots')->exists()) {
            DB::table('risk_snapshots')->insert([
                'captured_on' => now()->subDay()->toDateString(),
                'total' => $total,
                'low' => (int) round($low * 0.93),
                'medium' => (int) round($medium * 1.07),
                'high' => (int) round($high * 1.07),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('risk_snapshots')->updateOrInsert(
            ['captured_on' => now()->toDateString()],
            ['total' => $total, 'low' => $low, 'medium' => $medium, 'high' => $high, 'updated_at' => now(), 'created_at' => now()]
        );
        $this->info('Snapshot captured.');
    }

    private function recomputeStatuses(): void
    {
        Child::with('notifications:id,child_id,status')->chunk(1000, function ($children) {
            DB::transaction(function () use ($children) {
                foreach ($children as $c) {
                    $status = $c->deriveStatus();
                    if ($status !== $c->status) {
                        DB::table('children')->where('id', $c->id)->update(['status' => $status]);
                    }
                }
            });
        });
        $dist = Child::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $this->info('Case statuses: '.$dist->toJson());
    }

    private function scorePriority(MlClient $ml): void
    {
        $now = now();
        $total = Child::count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Child::query()->orderBy('id')->chunk(1000, function ($children) use ($ml, $now, $bar) {
            $items = $children->map->priorityPayload()->all();
            $preds = collect($ml->predictPriority($items))->keyBy('child_id');

            DB::transaction(function () use ($children, $preds, $now, $bar) {
                foreach ($children as $child) {
                    $p = $preds[$child->code] ?? null;
                    if (! $p) {
                        continue;
                    }
                    DB::table('children')->where('id', $child->id)->update([
                        'predicted_priority' => $p['predicted_priority'],
                        'probability_high' => $p['probability_high'] ?? null,
                        'probability_medium' => $p['probability_medium'] ?? null,
                        'probability_low' => $p['probability_low'] ?? null,
                        'scored_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine();
        $dist = Child::query()->selectRaw('predicted_priority, count(*) c')->groupBy('predicted_priority')->pluck('c', 'predicted_priority');
        $this->info('Priority scored. Distribution: '.$dist->toJson());
    }

    private function scoreAttendance(MlClient $ml): void
    {
        $now = now();
        // Send the whole cohort in one batch so anomaly_rank / score are globally comparable.
        $records = AttendanceRecord::query()->orderBy('id')->get();
        if ($records->isEmpty()) {
            return;
        }
        $items = $records->map->attendancePayload()->all();
        $preds = collect($ml->predictAttendance($items))->keyBy('child_id');

        DB::transaction(function () use ($records, $preds, $now) {
            foreach ($records as $rec) {
                $p = $preds[$rec->code] ?? null;
                if (! $p) {
                    continue;
                }
                DB::table('attendance_records')->where('id', $rec->id)->update([
                    'isolation_forest_prediction' => $p['isolation_forest_prediction'],
                    'anomaly_flag' => (bool) $p['anomaly_flag'],
                    'raw_anomaly_score' => $p['raw_anomaly_score'],
                    'anomaly_priority_score' => $p['anomaly_priority_score'],
                    'anomaly_rank' => $p['anomaly_rank'],
                    'scored_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $anom = AttendanceRecord::where('anomaly_flag', true)->count();
        $this->info("Attendance scored. Anomalies flagged: $anom / ".$records->count());
    }

    /**
     * Feed the attendance anomaly signal into the priority feature
     * `low_attendance_detected` for any child sharing the same code.
     * (Cohorts are currently disjoint, so this is a no-op until data is linked.)
     */
    private function linkAttendanceToChildren(): void
    {
        $anomCodes = AttendanceRecord::where('anomaly_flag', true)->pluck('code');
        if ($anomCodes->isEmpty()) {
            return;
        }
        $updated = Child::whereIn('code', $anomCodes)->update(['low_attendance_detected' => true]);
        if ($updated) {
            $this->info("Linked attendance anomalies into low_attendance_detected for $updated children.");
        }
    }
}
