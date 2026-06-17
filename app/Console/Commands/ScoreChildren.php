<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
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
        }

        $this->info('Done.');

        return self::SUCCESS;
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
