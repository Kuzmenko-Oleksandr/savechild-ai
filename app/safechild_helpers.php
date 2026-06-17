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
                'photo' => "/photos/child-$photoNum.png",
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

if (! function_exists('sc_level')) {
    function sc_level(?string $priority): ?string
    {
        return $priority ? ucfirst(strtolower($priority)) : null;
    }
}

if (! function_exists('sc_event_category')) {
    function sc_event_category(string $source): string
    {
        return ['police' => 'Incident', 'school' => 'Education', 'medical' => 'Social'][$source] ?? 'Social';
    }
}

if (! function_exists('sc_latest_events')) {
    /** Latest event per child id => Event. */
    function sc_latest_events(array $childIds): array
    {
        if (! $childIds) {
            return [];
        }
        $events = \App\Models\Event::query()
            ->whereIn('child_id', $childIds)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get();

        $latest = [];
        foreach ($events as $e) {
            if (! isset($latest[$e->child_id])) {
                $latest[$e->child_id] = $e;
            }
        }

        return $latest;
    }
}

if (! function_exists('sc_row')) {
    function sc_row(\App\Models\Child $c, ?\App\Models\Event $ev): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'level' => sc_level($c->predicted_priority) ?? 'Low',
            'age' => $c->age.' years',
            'school' => $c->school,
            'event' => $ev?->event_name ?? '—',
            'eventDate' => $ev?->event_date ? '('.$ev->event_date->format('d M Y').')' : '',
            'updated' => $ev?->event_date ? $ev->event_date->format('d M Y') : '—',
            'photo' => $c->photo,
        ];
    }
}

if (! function_exists('sc_notif')) {
    function sc_notif(\App\Models\CaseNotification $n, bool $withChild = false): array
    {
        $legacyBodyHash = 'dbaa37f5509bb5ffd47e2536c0289a9127162fb084be4fbd64d96f992cd3a0cc';
        $legacyNoteHash = 'fb13c966b8cddaab256b4b3684cc53639727e32c84743653e9b2cbc74e33363c';
        $body = $n->type === 'risk_elevated' || hash('sha256', (string) $n->body) === $legacyBodyHash
            ? sc_notification_body($n)
            : $n->body;
        $attachments = collect($n->attachments ?? [])->map(function ($attachment) use ($legacyNoteHash) {
            if (hash('sha256', (string) ($attachment['note'] ?? '')) === $legacyNoteHash) {
                $attachment['note'] = 'Information about a police visit to the residential address.';
            }

            return $attachment;
        })->all();

        $data = [
            'id' => $n->id,
            'childId' => $n->child_id,
            'status' => $n->status,
            'title' => $n->title,
            'body' => $body,
            'created' => $n->created_at?->diffForHumans() ?? 'now',
            'read' => (bool) $n->read_at,
            'attachments' => $attachments,
            'resolvedBy' => $n->resolved_by,
            'resolvedAt' => $n->resolved_at?->format('d M Y, H:i'),
        ];
        if ($withChild && $n->relationLoaded('child') && $n->child) {
            $data['child'] = ['id' => $n->child->id, 'name' => $n->child->name, 'photo' => $n->child->photo];
        }

        return $data;
    }
}

if (! function_exists('sc_notification_body')) {
    function sc_notification_body(\App\Models\CaseNotification $n): string
    {
        $child = $n->relationLoaded('child') && $n->child
            ? $n->child
            : \App\Models\Child::query()->find($n->child_id);

        $events = \App\Models\Event::query()
            ->where('child_id', $n->child_id)
            ->orderByDesc('event_date')
            ->orderByDesc('score')
            ->limit(8)
            ->get();

        $priority = sc_level($child?->predicted_priority) ?? 'High';
        $eventNames = $events
            ->sortByDesc('score')
            ->take(3)
            ->map(fn ($event) => sc_event_phrase($event))
            ->filter()
            ->values()
            ->all();

        $sources = $events
            ->pluck('event_source')
            ->filter()
            ->unique()
            ->map(fn ($source) => [
                'school' => 'school',
                'medical' => 'medical',
                'police' => 'police',
            ][$source] ?? $source)
            ->values()
            ->all();

        $found = $eventNames
            ? 'It found '.sc_join_words($eventNames).'.'
            : 'It found several recorded risk indicators in the case history.';

        $scope = $sources
            ? 'across '.sc_join_words($sources).' records'
            : 'across the available case records';

        $recommendations = sc_notification_recommendations($events, $child);

        return "Over the last three months, the system detected several risk factors that may indicate a decline in the child's well-being. {$found}\n\n"
            ."The available data shows recurring events {$scope}, which increased the risk level to {$priority}.\n\n"
            .$recommendations;
    }
}

if (! function_exists('sc_event_phrase')) {
    function sc_event_phrase(\App\Models\Event $event): string
    {
        $name = trim((string) $event->event_name);
        if ($name === '') {
            $name = \Illuminate\Support\Str::headline((string) $event->event_type);
        }

        return match ($event->event_source) {
            'school' => strtolower($name).' from the educational institution',
            'police' => strtolower($name).' that resulted in a police record',
            'medical' => strtolower($name).' from medical or support services',
            default => strtolower($name),
        };
    }
}

if (! function_exists('sc_notification_recommendations')) {
    function sc_notification_recommendations(\Illuminate\Support\Collection $events, ?\App\Models\Child $child): string
    {
        $types = $events->pluck('event_type')->map(fn ($type) => (string) $type)->all();
        $sources = $events->pluck('event_source')->map(fn ($source) => (string) $source)->all();
        $actions = [];

        if (in_array('police', $sources, true) || collect($types)->contains(fn ($type) => str_contains($type, 'violence') || str_contains($type, 'police'))) {
            $actions[] = "reviewing the child's living conditions";
        }
        if (in_array('school', $sources, true) || collect($types)->contains(fn ($type) => str_contains($type, 'attendance') || str_contains($type, 'absence') || str_contains($type, 'school'))) {
            $actions[] = 'clarifying school attendance and disciplinary details';
        }
        if (in_array('medical', $sources, true) || (bool) ($child?->chronic_health_condition)) {
            $actions[] = 'checking whether medical or psychological support is needed';
        }
        if ((bool) ($child?->active_social_case) || (bool) ($child?->single_parent) || (bool) ($child?->parent_unemployed)) {
            $actions[] = 'considering whether the family needs additional social support';
        }

        if (! $actions) {
            $actions[] = "reviewing the child's current situation";
            $actions[] = 'clarifying the latest recorded risk events';
            $actions[] = 'checking whether additional family support is needed';
        }

        return 'A review is recommended, including '.sc_join_words(array_slice(array_values(array_unique($actions)), 0, 3)).'.';
    }
}

if (! function_exists('sc_join_words')) {
    function sc_join_words(array $items): string
    {
        $items = array_values(array_filter($items));
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        return implode(', ', array_slice($items, 0, -1)).' and '.$items[array_key_last($items)];
    }
}

if (! function_exists('sc_trends_from_events')) {
    /** Build the High-Risk trend chart from real weekly event counts. */
    function sc_trends_from_events(): array
    {
        $rows = \App\Models\Event::query()
            ->selectRaw("date_trunc('week', event_date) as wk, count(*) c")
            ->whereNotNull('event_date')
            ->groupByRaw("date_trunc('week', event_date)")
            ->orderByRaw("date_trunc('week', event_date)")
            ->get();

        $weeks = $rows->map(fn ($r) => [
            'date' => \Illuminate\Support\Carbon::parse($r->wk),
            'c' => (int) $r->c,
        ])->values()->all();

        $build = function (string $id, array $slice) {
            if (! $slice) {
                return ['id' => $id, 'range' => '—', 'labels' => [], 'points' => [0]];
            }
            $n = count($slice);
            $points = array_map(fn ($w) => $w['c'], $slice);
            $idx = array_values(array_unique([0, (int) ($n * 0.25), (int) ($n * 0.5), (int) ($n * 0.75), $n - 1]));
            $labels = array_map(fn ($i) => $slice[$i]['date']->format('d.m'), $idx);
            $range = $slice[0]['date']->format('d M').' - '.$slice[$n - 1]['date']->format('d M');

            return ['id' => $id, 'range' => $range, 'labels' => $labels, 'points' => $points];
        };

        return [
            $build('30d', array_slice($weeks, -5)),
            $build('90d', array_slice($weeks, -13)),
            $build('all', $weeks),
        ];
    }
}
