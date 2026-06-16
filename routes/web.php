<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// SafeChild demo data helpers live in app/safechild_helpers.php (composer
// autoload.files) so they remain defined even when routes are cached.

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
