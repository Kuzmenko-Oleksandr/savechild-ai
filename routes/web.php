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
    $school = $request->string('school')->toString();
    $sex = array_values(array_intersect((array) $request->input('sex', []), ['Male', 'Female']));
    $events = array_values((array) $request->input('event', []));
    $ageBuckets = array_values((array) $request->input('age', []));
    $period = (int) $request->integer('period'); // days; 0 = any

    $ageRanges = ['0-5' => [0, 5], '6-10' => [6, 10], '11-14' => [11, 14], '15-17' => [15, 17]];

    $all = collect(safechild_dataset());

    $filtered = $all
        ->when(in_array($level, ['High', 'Medium', 'Low'], true), fn ($c) => $c->where('level', $level))
        ->when($school !== '', fn ($c) => $c->where('school', $school))
        ->when(! empty($sex), fn ($c) => $c->whereIn('sex', $sex))
        ->when(! empty($events), fn ($c) => $c->whereIn('event', $events))
        ->when(! empty($ageBuckets), function ($c) use ($ageBuckets, $ageRanges) {
            return $c->filter(function ($r) use ($ageBuckets, $ageRanges) {
                $age = (int) $r['age'];
                foreach ($ageBuckets as $b) {
                    if (isset($ageRanges[$b]) && $age >= $ageRanges[$b][0] && $age <= $ageRanges[$b][1]) {
                        return true;
                    }
                }

                return false;
            });
        })
        ->when($period > 0, fn ($c) => $c->filter(fn ($r) => $r['updated_days'] <= $period))
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
        'filters' => [
            'q' => $q,
            'level' => $level ?: 'All',
            'school' => $school,
            'sex' => $sex,
            'event' => $events,
            'age' => $ageBuckets,
            'period' => $period ?: null,
        ],
        'filterOptions' => [
            'schools' => $all->pluck('school')->unique()->sort()->values()->all(),
            'events' => $all->pluck('event')->unique()->sort()->values()->all(),
            'sexes' => [['value' => 'Male', 'label' => 'Boys'], ['value' => 'Female', 'label' => 'Girls']],
            'ages' => ['0-5', '6-10', '11-14', '15-17'],
            'periods' => [['value' => 30, 'label' => 'Last 30 days'], ['value' => 180, 'label' => 'Last 6 months'], ['value' => 365, 'label' => 'Last 12 months']],
        ],
    ]);
})->name('children.index');

Route::get('/children/{child}', function (int $child) {
    $row = collect(safechild_dataset())->firstWhere('id', $child) ?? safechild_dataset()[0];

    return Inertia::render('safechild/Child', [
        'child' => [
            'id' => $row['id'],
            'name' => $row['name'],
            'riskLabel' => $row['level'],
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
            "Assess the child's living conditions and safety",
            'Contact the school to clarify the situation.',
            "Evaluate the family's need for social support.",
        ],
        'eventHistory' => [
            ['date' => 'June 15, 2026', 'time' => '6:40 PM', 'title' => 'Police Report', 'category' => 'Incident', 'months' => 0, 'description' => 'A report of a family conflict at the home address was received. A preventive discussion was held with the parents.'],
            ['date' => 'May 28, 2026', 'time' => '9:15 AM', 'title' => 'School Absences', 'category' => 'Education', 'months' => 1, 'description' => 'The student accumulated 18 unexcused absences during May 2026. The school reported repeated missed classes and a decline in attendance.'],
            ['date' => 'April 12, 2026', 'time' => '2:20 PM', 'title' => 'School Disciplinary Action', 'category' => 'Education', 'months' => 2, 'description' => 'A disciplinary action was issued following repeated violations of school rules and a conflict involving another student. The incident was documented by the school administration.'],
            ['date' => 'November 03, 2025', 'time' => '11:05 AM', 'title' => 'Counselling Session', 'category' => 'Social', 'months' => 7, 'description' => 'A social worker held an introductory counselling session with the family to assess living conditions and support needs.'],
            ['date' => 'March 22, 2025', 'time' => '4:50 PM', 'title' => 'Initial Risk Assessment', 'category' => 'Social', 'months' => 14, 'description' => 'The case was opened and an initial risk assessment was completed after a referral from the school.'],
        ],
        'riskFactors' => [
            ['label' => 'School', 'value' => 41, 'color' => 'bg-red-500'],
            ['label' => 'Family Conflicts', 'value' => 27, 'color' => 'bg-orange-500'],
            ['label' => 'Socio-Economic', 'value' => 16, 'color' => 'bg-amber-400'],
            ['label' => 'Behavioral Issues', 'value' => 10, 'color' => 'bg-sky-400'],
            ['label' => 'Other', 'value' => 6, 'color' => 'bg-indigo-500'],
        ],
    ]);
})->name('children.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
