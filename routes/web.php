<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Deterministic demo dataset (~5000 children) for the SafeChild frontend.
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
            'photo' => "/photos/child-$photoNum.jpg",
        ];
    }

    return $cache = $rows;
}

function safechild_trends(): array
{
    return [
        ['id' => 'feb', 'range' => '05 Feb - 06 March', 'labels' => ['05.02', '12.02', '19.02', '26.02', '06.03'], 'points' => [700, 720, 820, 690, 760, 880, 620, 1000, 760, 850, 690, 800, 720, 760, 900]],
        ['id' => 'mar', 'range' => '07 March - 06 April', 'labels' => ['07.03', '14.03', '21.03', '28.03', '06.04'], 'points' => [620, 580, 640, 720, 680, 540, 600, 720, 800, 760, 690, 640, 580, 700, 760]],
        ['id' => 'apr', 'range' => '07 April - 06 May', 'labels' => ['07.04', '14.04', '21.04', '28.04', '06.05'], 'points' => [500, 560, 520, 480, 600, 660, 720, 640, 580, 700, 760, 820, 880, 900, 960]],
    ];
}

Route::get('/', fn () => Inertia::render('safechild/Dashboard', [
    'district' => 'Shevchenkivskyi district',
    'asOf' => 'Today, 10:30',
    'stats' => [
        ['key' => 'all', 'label' => 'All children', 'value' => '7 842'],
        ['key' => 'low', 'label' => 'Low Risk', 'value' => '6 194', 'delta' => '9.05%', 'trend' => 'up'],
        ['key' => 'medium', 'label' => 'Medium Risk', 'value' => '1 243', 'delta' => '9.05%', 'trend' => 'down'],
        ['key' => 'high', 'label' => 'High Risk', 'value' => '405', 'delta' => '9.05%', 'trend' => 'down'],
    ],
    'children' => collect(safechild_dataset())->where('level', 'High')->take(5)->values()->all(),
    'today' => [
        ['title' => '14 children moved', 'description' => 'children escalated to high risk'],
        ['title' => '27 new incidents', 'description' => 'were recorded in the system'],
        ['title' => '3 communities', 'description' => 'show a significant increase in risk levels'],
        ['title' => '3 communities', 'description' => 'cases require cross-agency coordination'],
    ],
    'trends' => safechild_trends(),
]))->name('home');

Route::get('/children', function (Request $request) {
    $perPageOptions = [10, 20, 50];
    $perPage = (int) $request->integer('per_page', 10);
    if (! in_array($perPage, $perPageOptions, true)) {
        $perPage = 10;
    }
    $level = $request->string('level')->toString();
    $q = trim($request->string('q')->toString());

    $filtered = collect(safechild_dataset())
        ->when(in_array($level, ['High', 'Medium', 'Low'], true), fn ($c) => $c->where('level', $level))
        ->when($q !== '', function ($c) use ($q) {
            $needle = mb_strtolower($q);

            return $c->filter(fn ($row) => str_contains(mb_strtolower($row['name']), $needle)
                || str_contains(mb_strtolower($row['school']), $needle));
        })
        ->values();

    $total = $filtered->count();
    $lastPage = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $request->integer('page', 1)), $lastPage);
    $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

    return Inertia::render('safechild/Children', [
        'children' => $items->all(),
        'pagination' => [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'per_page_options' => $perPageOptions,
            'total' => $total,
            'from' => $total ? ($page - 1) * $perPage + 1 : 0,
            'to' => $total ? ($page - 1) * $perPage + $items->count() : 0,
        ],
        'filters' => ['q' => $q, 'level' => $level ?: 'All'],
    ]);
})->name('children.index');

Route::get('/children/{child}', function (int $child) {
    $row = collect(safechild_dataset())->firstWhere('id', $child) ?? safechild_dataset()[0];

    return Inertia::render('safechild/Child', [
        'child' => [
            'id' => $row['id'],
            'name' => $row['name'],
            'riskLabel' => 'High 75/100',
            'photo' => $row['photo'],
            'age' => $row['age'],
            'sex' => $row['sex'],
            'school' => 'Lyceum №38',
            'grade' => '6-B',
            'guardians' => 'Olena Petrenko, Serhii Petrenko',
            'contact' => '+380 67 123 45 67',
            'address' => '12 Shevchenka St., Apt. 45, Kyiv',
        ],
        'aiSummary' => 'Over the past three months, multiple risk indicators have been identified, including a police-reported family conflict, recurring school absenteeism, and a disciplinary incident at school.',
        'recommendations' => [
            ['title' => "Assess the child's living conditions and safety", 'description' => 'recommended home visit within 5 days'],
            ['title' => 'Coordinate with school counsellor', 'description' => 'review the absenteeism pattern and triggers'],
        ],
        'riskFactors' => [
            ['label' => 'School', 'value' => 41, 'color' => 'bg-red-500'],
            ['label' => 'Family Conflicts', 'value' => 27, 'color' => 'bg-orange-500'],
            ['label' => 'Socio-Economic', 'value' => 16, 'color' => 'bg-amber-400'],
            ['label' => 'Behavioral Issues', 'value' => 10, 'color' => 'bg-sky-400'],
            ['label' => 'Behavioral Issues', 'value' => 10, 'color' => 'bg-sky-400'],
            ['label' => 'Other', 'value' => 6, 'color' => 'bg-indigo-500'],
        ],
    ]);
})->name('children.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
