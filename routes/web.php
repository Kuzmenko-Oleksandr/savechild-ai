<?php

use App\Models\Child;
use App\Models\Event;
use App\Services\AiSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', function () {
    $byPriority = Child::query()
        ->selectRaw('predicted_priority, count(*) c')
        ->groupBy('predicted_priority')->pluck('c', 'predicted_priority');
    $low = (int) ($byPriority['LOW'] ?? 0);
    $medium = (int) ($byPriority['MEDIUM'] ?? 0);
    $high = (int) ($byPriority['HIGH'] ?? 0);
    $all = Child::count();

    $fmt = fn (int $n) => number_format($n, 0, '.', ' ');

    // Dynamic deltas from the two most recent risk snapshots.
    $snaps = DB::table('risk_snapshots')->orderByDesc('captured_on')->limit(2)->get();
    $cur = $snaps[0] ?? null;
    $prev = $snaps[1] ?? null;
    $delta = function (string $key) use ($cur, $prev) {
        if (! $cur || ! $prev || ($prev->$key ?? 0) == 0) {
            return [null, 'up'];
        }
        $pct = (($cur->$key - $prev->$key) / $prev->$key) * 100;

        return [number_format(abs($pct), 2).'%', $pct >= 0 ? 'up' : 'down'];
    };
    [$dl, $tl] = $delta('low');
    [$dm, $tm] = $delta('medium');
    [$dh, $th] = $delta('high');

    $highChildren = Child::query()->where('predicted_priority', 'HIGH')->orderByDesc('probability_high')->take(5)->get();
    $latest = sc_latest_events($highChildren->pluck('id')->all());

    // Dynamic "Today" figures.
    $eventsRecent = Event::where('event_date', '>=', now()->subDays(30))->count();
    $underSupervision = Child::where('status', 'under_supervision')->count();
    $resolvedToday = \App\Models\CaseNotification::where('status', 'resolved')->whereDate('resolved_at', now()->toDateString())->count();

    return Inertia::render('safechild/Dashboard', [
        'district' => 'Shevchenkivskyi district',
        'asOf' => now()->format('d M Y, H:i'),
        'stats' => [
            ['key' => 'all', 'label' => 'All children', 'value' => $fmt($all)],
            ['key' => 'low', 'label' => 'Low Risk', 'value' => $fmt($low), 'delta' => $dl, 'trend' => $tl],
            ['key' => 'medium', 'label' => 'Medium Risk', 'value' => $fmt($medium), 'delta' => $dm, 'trend' => $tm],
            ['key' => 'high', 'label' => 'High Risk', 'value' => $fmt($high), 'delta' => $dh, 'trend' => $th],
        ],
        'children' => $highChildren->map(fn ($c) => sc_row($c, $latest[$c->id] ?? null))->all(),
        'today' => [
            ['title' => $fmt($high).' children at high risk', 'description' => 'flagged by the priority model'],
            ['title' => $fmt($eventsRecent).' new incidents', 'description' => 'recorded in the last 30 days'],
            ['title' => $fmt($underSupervision).' cases under supervision', 'description' => 'currently in progress'],
            ['title' => $fmt($resolvedToday).' cases resolved', 'description' => 'marked done today'],
        ],
        'trends' => sc_trends_from_events(),
    ]);
})->name('home');

Route::get('/mainadmin', function () {
    $byPriority = Child::query()
        ->selectRaw('predicted_priority, count(*) c')
        ->groupBy('predicted_priority')->pluck('c', 'predicted_priority');
    $low = (int) ($byPriority['LOW'] ?? 0);
    $medium = (int) ($byPriority['MEDIUM'] ?? 0);
    $high = (int) ($byPriority['HIGH'] ?? 0);
    $all = Child::count();

    $fmt = fn (int $n) => number_format($n, 0, '.', ' ');

    // Dynamic deltas from the two most recent risk snapshots.
    $snaps = DB::table('risk_snapshots')->orderByDesc('captured_on')->limit(2)->get();
    $cur = $snaps[0] ?? null;
    $prev = $snaps[1] ?? null;
    $delta = function (string $key) use ($cur, $prev) {
        if (! $cur || ! $prev || ($prev->$key ?? 0) == 0) {
            return [null, 'up'];
        }
        $pct = (($cur->$key - $prev->$key) / $prev->$key) * 100;

        return [number_format(abs($pct), 2).'%', $pct >= 0 ? 'up' : 'down'];
    };
    [$dl, $tl] = $delta('low');
    [$dm, $tm] = $delta('medium');
    [$dh, $th] = $delta('high');

    // Analytics donut: case notifications by status.
    $byStatus = \App\Models\CaseNotification::query()
        ->selectRaw('status, count(*) c')
        ->groupBy('status')->pluck('c', 'status');
    $checkItOut = (int) ($byStatus['check_it_out'] ?? 0);
    $inProgress = (int) ($byStatus['in_progress'] ?? 0);
    $resolved = (int) ($byStatus['resolved'] ?? 0);
    $totalCases = $checkItOut + $inProgress + $resolved;

    // Case closing efficiency leaderboard (demo — would be a workers table in prod).
    $efficiency = [
        ['name' => 'Martin Kask', 'role' => 'Social Worker', 'percent' => 82, 'color' => 'bg-emerald-500', 'photo' => 'https://i.pravatar.cc/96?img=12', 'initials' => 'MK'],
        ['name' => 'Andrii Melnyk', 'role' => 'Child Psychologist', 'percent' => 75, 'color' => 'bg-amber-400', 'photo' => 'https://i.pravatar.cc/96?img=11', 'initials' => 'AM'],
        ['name' => 'Iryna Bondarenko', 'role' => 'Juvenile Prevention Officer', 'percent' => 60, 'color' => 'bg-amber-400', 'photo' => 'https://i.pravatar.cc/96?img=5', 'initials' => 'IB'],
        ['name' => 'Liis Tamm', 'role' => 'Family Support Specialist', 'percent' => 32, 'color' => 'bg-amber-400', 'photo' => 'https://i.pravatar.cc/96?img=9', 'initials' => 'LT'],
        ['name' => 'Sander Saar', 'role' => 'Child Rights Coordinator', 'percent' => 10, 'color' => 'bg-red-500', 'photo' => 'https://i.pravatar.cc/96?img=13', 'initials' => 'SS'],
    ];

    $eventsRecent = Event::where('event_date', '>=', now()->subDays(30))->count();
    $underSupervision = Child::where('status', 'under_supervision')->count();
    $resolvedToday = \App\Models\CaseNotification::where('status', 'resolved')->whereDate('resolved_at', now()->toDateString())->count();

    return Inertia::render('safechild/MainAdmin', [
        'district' => 'Shevchenkivskyi district',
        'asOf' => now()->format('d M Y, H:i'),
        'currentUser' => ['name' => 'Martin Kask', 'photo' => 'https://i.pravatar.cc/80?img=12', 'initials' => 'MK'],
        'admin' => ['name' => 'Martin Kask', 'photo' => 'https://i.pravatar.cc/80?img=12'],
        'stats' => [
            ['key' => 'all', 'label' => 'All children', 'value' => $fmt($all)],
            ['key' => 'low', 'label' => 'Low Risk', 'value' => $fmt($low), 'delta' => $dl, 'trend' => $tl],
            ['key' => 'medium', 'label' => 'Medium Risk', 'value' => $fmt($medium), 'delta' => $dm, 'trend' => $tm],
            ['key' => 'high', 'label' => 'High Risk', 'value' => $fmt($high), 'delta' => $dh, 'trend' => $th],
        ],
        'analytics' => [
            'total' => $totalCases,
            'checkItOut' => $checkItOut,
            'inProgress' => $inProgress,
            'resolved' => $resolved,
        ],
        'efficiency' => $efficiency,
        'today' => [
            ['title' => $fmt($high).' children at high risk', 'description' => 'flagged by the priority model'],
            ['title' => $fmt($eventsRecent).' new incidents', 'description' => 'recorded in the last 30 days'],
            ['title' => $fmt($underSupervision).' cases under supervision', 'description' => 'currently in progress'],
            ['title' => $fmt($resolvedToday).' cases resolved', 'description' => 'marked done today'],
        ],
        'trends' => sc_trends_from_events(),
    ]);
})->name('mainadmin');

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
    $statuses = array_values(array_intersect((array) $request->input('status', []), Child::STATUSES));
    $sort = $request->string('sort')->toString();
    $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

    $ageRanges = ['0-5' => [0, 5], '6-10' => [6, 10], '11-14' => [11, 14], '15-17' => [15, 17]];

    $query = Child::query()
        ->addSelect('children.*')
        ->addSelect(['last_event_date' => Event::select('event_date')
            ->whereColumn('events.child_id', 'children.id')
            ->orderByDesc('event_date')->orderByDesc('id')->limit(1)])
        ->when(in_array($level, ['HIGH', 'MEDIUM', 'LOW'], true), fn ($x) => $x->where('predicted_priority', $level))
        ->when($school !== '', fn ($x) => $x->where('school', $school))
        ->when(! empty($sex), fn ($x) => $x->whereIn('sex', $sex))
        ->when(! empty($statuses), fn ($x) => $x->whereIn('status', $statuses))
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
        ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'ilike', "%$q%")->orWhere('school', 'ilike', "%$q%")));

    // Sorting (all columns). Unspecified => risk priority then id.
    $sortMap = [
        'child' => 'name',
        'age' => 'age',
        'school' => 'school',
        'status' => 'status',
        'risk' => "array_position(ARRAY['HIGH','MEDIUM','LOW']::text[], predicted_priority)",
        'event' => 'last_event_date',
        'updated' => 'last_event_date',
    ];
    if (isset($sortMap[$sort])) {
        $query->orderByRaw($sortMap[$sort].' '.$dir.' nulls last');
    } else {
        $query->orderByRaw("array_position(ARRAY['HIGH','MEDIUM','LOW']::text[], predicted_priority)")->orderBy('id');
    }

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
            'status' => $statuses,
        ],
        'sort' => ['by' => $sort ?: null, 'dir' => $dir],
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
            'statuses' => [
                ['value' => 'new', 'label' => 'New cases'],
                ['value' => 'needs_attention', 'label' => 'Needs attention'],
                ['value' => 'under_supervision', 'label' => 'Under supervision'],
                ['value' => 'closed', 'label' => 'Closed'],
            ],
        ],
    ]);
})->name('children.index');

Route::get('/children/{child}/ai-summary', function (Child $child) {
    $events = $child->events()->orderByDesc('event_date')->orderByDesc('id')->get();
    $aiSummary = app(AiSummaryService::class)->forChild($child, $events);

    return response()->json([
        'aiSummary' => $aiSummary['summary'],
        'aiKeyFactors' => $aiSummary['keyFactors'],
        'aiDisclaimer' => $aiSummary['disclaimer'],
        'recommendations' => $aiSummary['recommendations'],
    ]);
})->name('children.ai-summary');

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
        'eventHistory' => $history,
        'riskFactors' => $riskFactors,
        'notifications' => $child->notifications()->latest()->get()->map(fn ($n) => sc_notif($n))->all(),
    ]);
})->name('children.show');

Route::get('/notifications', function (Request $request) {
    $status = $request->string('status')->toString();
    $tabMap = ['check_it_out' => 'check_it_out', 'in_progress' => 'in_progress', 'done' => 'resolved'];

    $page = \App\Models\CaseNotification::query()
        ->with('child:id,name,photo')
        ->when(isset($tabMap[$status]), fn ($x) => $x->where('status', $tabMap[$status]))
        ->latest()
        ->paginate(8)
        ->withQueryString();

    return Inertia::render('safechild/Notifications', [
        'items' => $page->getCollection()->map(fn ($n) => sc_notif($n, true))->all(),
        'pagination' => [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'per_page_options' => [8],
            'total' => $page->total(),
            'from' => $page->firstItem() ?? 0,
            'to' => $page->lastItem() ?? 0,
        ],
        'tab' => $status ?: 'all',
    ]);
})->name('notifications.index');

Route::post('/notifications/{notification}/take', function (\App\Models\CaseNotification $notification) {
    $notification->update(['status' => 'in_progress', 'read_at' => $notification->read_at ?? now()]);
    $notification->child->update(['status' => $notification->child->fresh()->load('notifications')->deriveStatus()]);

    return back();
})->name('notifications.take');

Route::post('/notifications/{notification}/resolve', function (\App\Models\CaseNotification $notification) {
    $notification->update([
        'status' => 'resolved',
        'resolved_by' => 'Olena Franko',
        'resolved_at' => now(),
        'read_at' => $notification->read_at ?? now(),
    ]);
    $notification->child->update(['status' => $notification->child->fresh()->load('notifications')->deriveStatus()]);

    return back();
})->name('notifications.resolve');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
