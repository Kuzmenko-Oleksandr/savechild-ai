<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SafeChildSeeder extends Seeder
{
    private array $maleFirst = ['Danylo', 'Andrii', 'Maksym', 'Oleh', 'Bohdan', 'Mykyta', 'Artem', 'Ivan', 'Yurii', 'Taras', 'Roman', 'Dmytro', 'Volodymyr', 'Serhii', 'Nazar'];

    private array $femaleFirst = ['Sofiia', 'Mariia', 'Yuliia', 'Kateryna', 'Anna', 'Olha', 'Iryna', 'Nataliia', 'Daryna', 'Solomiia', 'Veronika', 'Khrystyna', 'Diana', 'Yana', 'Zlata'];

    private array $surnames = ['Petrenko', 'Bondarenko', 'Shevchuk', 'Symonenko', 'Kravets', 'Kovalenko', 'Tkachenko', 'Marchenko', 'Ivanenko', 'Lysenko', 'Melnyk', 'Boyko', 'Kovalchuk', 'Shevchenko', 'Tkachuk'];

    private array $schools = ['Lyceum No. 38', 'School No. 5', 'School No. 73', 'Specialized School No. 53', 'Kyiv Lyceum No. 142', 'School No. 12', 'School No. 8', 'School No. 24', 'Lyceum No. 3', 'Gymnasium No. 9'];

    private const BOOL_FEATURES = [
        'region_frontline', 'idp_status', 'single_parent', 'parent_unemployed',
        'chronic_health_condition', 'active_social_case', 'low_attendance_detected',
        'academic_performance_drop', 'school_transfer', 'school_behavior_incident',
        'missed_scheduled_vaccination', 'injury_report', 'unexplained_injury_concern',
        'police_referral', 'domestic_violence_report', 'psychological_support_recommended',
        'neglect_concern_observed',
    ];

    public function run(): void
    {
        $now = now();

        DB::table('events')->delete();
        DB::table('attendance_records')->delete();
        DB::table('children')->delete();

        $this->seedChildren($now);
        $this->seedEvents($now);
        $this->seedAttendance($now);
    }

    private function seedChildren(CarbonInterface $now): void
    {
        $rows = [];
        $i = 0;
        foreach ($this->readCsv(database_path('data/train_data.csv')) as $r) {
            $i++;
            $isMale = $i % 2 === 1;
            $first = $isMale ? $this->maleFirst[($i * 3) % 15] : $this->femaleFirst[($i * 3) % 15];
            $surname = $this->surnames[($i * 5) % 15];
            $photoNum = $isMale ? [1, 3, 5, 7, 9][($i * 2) % 5] : [2, 4, 6, 8, 10][($i * 2) % 5];

            $row = [
                'code' => $r['child_id'],
                'name' => "$first $surname",
                'sex' => $isMale ? 'Male' : 'Female',
                'school' => $this->schools[($i * 11) % 10],
                'grade' => (1 + ((int) $r['age']) % 11).'-'.['A', 'B', 'V'][$i % 3],
                'guardians' => 'Olena Petrenko, Serhii Petrenko',
                'contact' => '+380 67 '.str_pad((string) (100 + $i % 900), 3, '0', STR_PAD_LEFT).' 45 67',
                'address' => (10 + $i % 80).' Shevchenka St., Apt. '.(1 + $i % 90).', Kyiv',
                'photo' => "/photos/child-$photoNum.jpg",
                'age' => (int) $r['age'],
                'age_group' => $r['age_group'],
                'household_income_level' => $r['household_income_level'],
                'num_siblings' => (int) $r['num_siblings'],
                'total_events' => (int) $r['total_events'],
                'school_events' => (int) $r['school_events'],
                'medical_events' => (int) $r['medical_events'],
                'police_events' => (int) $r['police_events'],
                'distinct_event_types' => (int) $r['distinct_event_types'],
                'risk_sources' => (int) $r['risk_sources'],
                'train_score' => (int) $r['score'],
                'train_label' => $r['priority_label'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach (self::BOOL_FEATURES as $f) {
                $row[$f] = (bool) ((int) $r[$f]);
            }
            $rows[] = $row;

            if (count($rows) >= 1000) {
                DB::table('children')->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('children')->insert($rows);
        }
        $this->command->info('Children seeded: '.DB::table('children')->count());
    }

    private function seedEvents(CarbonInterface $now): void
    {
        $idByCode = DB::table('children')->pluck('id', 'code');
        $rows = [];
        $skipped = 0;
        foreach ($this->readCsv(database_path('data/train_event_log.csv')) as $r) {
            $childId = $idByCode[$r['child_id']] ?? null;
            if (! $childId) {
                $skipped++;

                continue;
            }
            $rows[] = [
                'child_id' => $childId,
                'event_source' => $r['event_source'],
                'event_type' => $r['event_type'],
                'event_name' => $r['event_name'],
                'event_date' => $this->date($r['event_date']),
                'score' => (int) $r['score'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) >= 2000) {
                DB::table('events')->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('events')->insert($rows);
        }
        $this->command->info('Events seeded: '.DB::table('events')->count()." (skipped $skipped without a matching child)");
    }

    private function seedAttendance(CarbonInterface $now): void
    {
        $rows = [];
        foreach ($this->readCsv(database_path('data/attendance_train_data.csv')) as $r) {
            $rows[] = [
                'code' => $r['child_id'],
                'absent_days' => (int) $r['absent_days'],
                'unexcused_absent_days' => (int) $r['unexcused_absent_days'],
                'late_days' => (int) $r['late_days'],
                'attendance_rate' => (float) $r['attendance_rate'],
                'max_consecutive_absent_days' => (int) $r['max_consecutive_absent_days'],
                'absence_days_last_14d' => (int) $r['absence_days_last_14d'],
                'absence_days_last_30d' => (int) $r['absence_days_last_30d'],
                'monday_friday_absences' => (int) $r['monday_friday_absences'],
                'synthetic_pattern' => $r['synthetic_pattern'] ?? null,
                'expected_anomaly_flag' => isset($r['expected_anomaly_flag']) ? (bool) ((int) $r['expected_anomaly_flag']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('attendance_records')->insert($rows);
        $this->command->info('Attendance records seeded: '.DB::table('attendance_records')->count());
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return \Generator<array<string,string>> */
    private function readCsv(string $path): \Generator
    {
        $h = fopen($path, 'r');
        if ($h === false) {
            throw new \RuntimeException("Cannot open CSV: $path");
        }
        $header = fgetcsv($h);
        while (($line = fgetcsv($h)) !== false) {
            yield array_combine($header, $line);
        }
        fclose($h);
    }
}
