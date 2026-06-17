<?php

use App\Models\Child;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function () {
    $byPriority = Child::query()
        ->selectRaw('predicted_priority, count(*) c')
        ->groupBy('predicted_priority')->pluck('c', 'predicted_priority');

    $fmt = fn (int $n) => number_format($n, 0, '.', ' ');

    $highChildren = Child::query()->where('predicted_priority', 'HIGH')->orderByDesc('probability_high')->take(5)->get();
    $latest = sc_latest_events($highChildren->pluck('id')->all());

    return Inertia::render('safechild/Dashboard', [
        'district' => 'Shevchenkivskyi district',
        'asOf' => now()->format('d M Y, H:i'),
        'stats' => [
            ['key' => 'all', 'label' => 'All children', 'value' => $fmt(Child::count())],
            ['key' => 'low', 'label' => 'Low Risk', 'value' => $fmt((int) ($byPriority['LOW'] ?? 0)), 'delta' => '9.05%', 'trend' => 'up'],
            ['key' => 'medium', 'label' => 'Medium Risk', 'value' => $fmt((int) ($byPriority['MEDIUM'] ?? 0)), 'delta' => '9.05%', 'trend' => 'down'],
            ['key' => 'high', 'label' => 'High Risk', 'value' => $fmt((int) ($byPriority['HIGH'] ?? 0)), 'delta' => '9.05%', 'trend' => 'down'],
        ],
        'children' => $highChildren->map(fn ($c) => sc_row($c, $latest[$c->id] ?? null))->all(),
        'today' => [
            ['title' => '14 children moved', 'description' => 'children escalated to high risk'],
            ['title' => '27 new incidents', 'description' => 'were recorded in the system'],
            ['title' => '3 communities', 'description' => 'show a significant increase in risk levels'],
            ['title' => '3 communities', 'description' => 'cases require cross-agency coordination'],
        ],
        'trends' => safechild_trends(),
    ]);
})->name('home');

Route::get('/children', function (Request $request) {
    $perPageOptions = [10, 20, 50];
    $perPage = (int) $request->integer('per_page', 10);
    if (! in_array($perPage, $perPageOptions, true)) {
        $perPage = 10;
    }
    $level = strtoupper($request->string('level')->toString());
    $q = trim($request->string('q')->toString());
    $school = $request->string('school')->toString();
    $sex = array_values(array_intersect((array) $request->input('sex', []), ['Male', 'Female']));
    $sources = array_values(array_intersect((array) $request->input('event', []), ['school', 'medical', 'police']));
    $ageBuckets = array_values((array) $request->input('age', []));
    $period = (int) $request->integer('period');

    $ageRanges = ['0-5' => [0, 5], '6-10' => [6, 10], '11-14' => [11, 14], '15-17' => [15, 17]];

    $query = Child::query()
        ->when(in_array($level, ['HIGH', 'MEDIUM', 'LOW'], true), fn ($x) => $x->where('predicted_priority', $level))
        ->when($school !== '', fn ($x) => $x->where('school', $school))
        ->when(! empty($sex), fn ($x) => $x->whereIn('sex', $sex))
        ->when(! empty($ageBuckets), function ($x) use ($ageBuckets, $ageRanges) {
            $x->where(function ($w) use ($ageBuckets, $ageRanges) {
                foreach ($ageBuckets as $b) {
                    if (isset($ageRanges[$b])) {
                        $w->orWhereBetween('age', $ageRanges[$b]);
                    }
                }
            });
        })
        ->when(! empty($sources), fn ($x) => $x->whereHas('events', fn ($e) => $e->whereIn('event_source', $sources)))
        ->when($period > 0, fn ($x) => $x->whereHas('events', fn ($e) => $e->where('event_date', '>=', now()->subDays($period))))
        ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'ilike', "%$q%")->orWhere('school', 'ilike', "%$q%")))
        ->orderByRaw("array_position(ARRAY['HIGH','MEDIUM','LOW']::text[], predicted_priority)")
        ->orderBy('id');

    $page = $query->paginate($perPage)->withQueryString();
    $latest = sc_latest_events($page->getCollection()->pluck('id')->all());

    return Inertia::render('safechild/Children', [
        'children' => $page->getCollection()->map(fn ($c) => sc_row($c, $latest[$c->id] ?? null))->all(),
        'pagination' => [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'per_page_options' => $perPageOptions,
            'total' => $page->total(),
            'from' => $page->firstItem() ?? 0,
            'to' => $page->lastItem() ?? 0,
        ],
        'filters' => [
            'q' => $q,
            'level' => $level ? ucfirst(strtolower($level)) : 'All',
            'school' => $school,
            'sex' => $sex,
            'event' => $sources,
            'age' => $ageBuckets,
            'period' => $period ?: null,
        ],
        'filterOptions' => [
            'schools' => Child::query()->select('school')->distinct()->orderBy('school')->pluck('school')->all(),
            'events' => [
                ['value' => 'school', 'label' => 'School'],
                ['value' => 'medical', 'label' => 'Medical'],
                ['value' => 'police', 'label' => 'Police'],
            ],
            'sexes' => [['value' => 'Male', 'label' => 'Boys'], ['value' => 'Female', 'label' => 'Girls']],
            'ages' => ['0-5', '6-10', '11-14', '15-17'],
            'periods' => [['value' => 30, 'label' => 'Last 30 days'], ['value' => 180, 'label' => 'Last 6 months'], ['value' => 365, 'label' => 'Last 12 months']],
        ],
    ]);
})->name('children.index');

Route::get('/children/{child}', function (Child $child) {
    $events = $child->events()->orderByDesc('event_date')->orderByDesc('id')->get();

    $history = $events->map(function (Event $e) {
        $months = $e->event_date ? (int) $e->event_date->diffInMonths(now()) : 0;

        return [
            'date' => $e->event_date?->format('F d, Y') ?? '',
            'time' => '',
            'title' => $e->event_name,
            'category' => sc_event_category($e->event_source),
            'months' => $months,
            'description' => Str::headline($e->event_type).' · score '.$e->score,
        ];
    })->values()->all();

    // Top risk factors from this child's events, grouped by type (by total score).
    $byType = $events->groupBy('event_type')->map(fn ($g) => $g->sum('score'))->sortDesc();
    $totalScore = max(1, $byType->sum());
    $palette = ['bg-red-500', 'bg-orange-500', 'bg-amber-400', 'bg-sky-400', 'bg-indigo-500'];
    $riskFactors = $byType->take(5)->values()->map(fn ($score, $i) => [
        'label' => Str::headline($byType->keys()[$i]),
        'value' => (int) round($score / $totalScore * 100),
        'color' => $palette[$i] ?? 'bg-neutral-400',
    ])->all();
    if (! $riskFactors) {
        $riskFactors = [['label' => 'No events', 'value' => 0, 'color' => 'bg-neutral-300']];
    }

    $concerns = collect(Child::PRIORITY_FEATURES)
        ->filter(fn ($f) => (bool) $child->{$f} && ! in_array($f, ['num_siblings'], true))
        ->map(fn ($f) => Str::headline($f))->take(4)->implode(', ');

    return Inertia::render('safechild/Child', [
        'child' => [
            'id' => $child->id,
            'name' => $child->name,
            'riskLabel' => sc_level($child->predicted_priority) ?? 'Low',
            'photo' => $child->photo,
            'age' => $child->age.' years',
            'sex' => $child->sex,
            'school' => $child->school,
            'grade' => $child->grade,
            'guardians' => $child->guardians,
            'contact' => $child->contact,
            'address' => $child->address,
        ],
        'aiSummary' => 'Model priority: '.($child->predicted_priority ?? 'not scored')
            .($concerns ? '. Active risk indicators: '.$concerns.'.' : '.')
            .' Based on '.$events->count().' recorded events across school, medical and police sources.',
        'recommendations' => [
            "Assess the child's living conditions and safety",
            'Contact the school to clarify the situation.',
            "Evaluate the family's need for social support.",
        ],
        'eventHistory' => $history,
        'riskFactors' => $riskFactors,
    ]);
})->name('children.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
