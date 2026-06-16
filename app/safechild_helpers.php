<?php

/**
 * SafeChild demo data helpers.
 *
 * Loaded via composer "autoload.files", so these functions are always defined
 * globally — including when routes are served from `route:cache` (closure
 * routes reference them and would otherwise hit "undefined function").
 */
if (! function_exists('safechild_dataset')) {
    /**
     * Deterministic demo dataset (~5000 children).
     * Photos are gender-matched: name gender === photo gender.
     */
    function safechild_dataset(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $maleFirst = ['Danylo', 'Andrii', 'Maksym', 'Oleh', 'Bohdan', 'Mykyta', 'Artem', 'Ivan', 'Yurii', 'Taras', 'Roman', 'Dmytro', 'Volodymyr', 'Serhii', 'Nazar'];
        $femaleFirst = ['Sofiia', 'Mariia', 'Yuliia', 'Kateryna', 'Anna', 'Olha', 'Iryna', 'Nataliia', 'Daryna', 'Solomiia', 'Veronika', 'Khrystyna', 'Diana', 'Yana', 'Zlata'];
        $surnames = ['Petrenko', 'Bondarenko', 'Shevchuk', 'Symonenko', 'Kravets', 'Kovalenko', 'Tkachenko', 'Marchenko', 'Ivanenko', 'Lysenko', 'Melnyk', 'Boyko', 'Kovalchuk', 'Shevchenko', 'Tkachuk'];
        $schools = ['Lyceum No. 38', 'School No. 5', 'School No. 73', 'Specialized School No. 53', 'Kyiv Lyceum No. 142', 'School No. 12', 'School No. 8', 'School No. 24', 'Lyceum No. 3', 'Gymnasium No. 9'];
        $events = [
            ['Police Incident', '31 May 2024'],
            ['School Absenteeism (28 absences)', '29 May 2024'],
            ['Family Conflict', '29 May 2024'],
            ['Counselling Session', '20 May 2024'],
            ['Disciplinary Incident', '18 May 2024'],
        ];
        $updates = ['Today, 09:45', 'Yesterday, 17:10', '02 Jun 2024', '01 Jun 2024', '31 May 2024'];
        $malePhotos = [1, 3, 5, 7, 9];
        $femalePhotos = [2, 4, 6, 8, 10];

        $rows = [];
        for ($i = 1; $i <= 5000; $i++) {
            $isMale = $i % 2 === 1;
            $first = $isMale ? $maleFirst[($i * 3) % count($maleFirst)] : $femaleFirst[($i * 3) % count($femaleFirst)];
            $surname = $surnames[($i * 5) % count($surnames)];
            $bucket = ($i * 13) % 100;
            $level = $bucket < 8 ? 'High' : ($bucket < 33 ? 'Medium' : 'Low');
            $event = $events[($i * 7) % count($events)];
            $photoNum = $isMale ? $malePhotos[($i * 2) % 5] : $femalePhotos[($i * 2) % 5];

            $rows[] = [
                'id' => $i,
                'name' => "$first $surname",
                'sex' => $isMale ? 'Male' : 'Female',
                'level' => $level,
                'age' => (9 + ($i % 7)).' years',
                'school' => $schools[($i * 11) % count($schools)],
                'event' => $event[0],
                'eventDate' => $event[1],
                'updated' => $updates[($i * 17) % count($updates)],
                'updated_days' => ($i * 37) % 400,
                'photo' => "/photos/child-$photoNum.jpg",
            ];
        }

        return $cache = $rows;
    }
}

if (! function_exists('safechild_trends')) {
    /** High-risk children trend ranges for the dashboard chart. */
    function safechild_trends(): array
    {
        return [
            ['id' => 'feb', 'range' => '05 Feb - 06 March', 'labels' => ['05.02', '12.02', '19.02', '26.02', '06.03'], 'points' => [700, 720, 820, 690, 760, 880, 620, 1000, 760, 850, 690, 800, 720, 760, 900]],
            ['id' => 'mar', 'range' => '07 March - 06 April', 'labels' => ['07.03', '14.03', '21.03', '28.03', '06.04'], 'points' => [620, 580, 640, 720, 680, 540, 600, 720, 800, 760, 690, 640, 580, 700, 760]],
            ['id' => 'apr', 'range' => '07 April - 06 May', 'labels' => ['07.04', '14.04', '21.04', '28.04', '06.05'], 'points' => [500, 560, 520, 480, 600, 660, 720, 640, 580, 700, 760, 820, 880, 900, 960]],
        ];
    }
}
