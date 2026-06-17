<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Child;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiSummaryService
{
    public function __construct(private readonly MlClient $ml)
    {
    }

    /** @param Collection<int,\App\Models\Event> $events */
    public function forChild(Child $child, Collection $events): array
    {
        $fallback = $this->fallback($child, $events);

        try {
            $response = $this->ml->summarizeChild($this->payload($child, $events));
        } catch (Throwable $e) {
            Log::warning('AI summary generation failed.', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }

        return [
            'summary' => (string) ($response['ai_summary'] ?? $fallback['summary']),
            'keyFactors' => array_values(array_filter($response['key_factors'] ?? [])),
            'recommendations' => array_values(array_filter($response['recommended_actions'] ?? $fallback['recommendations'])),
            'disclaimer' => (string) ($response['disclaimer'] ?? ''),
            'payload' => $this->payload($child, $events),
        ];
    }

    /** @param Collection<int,\App\Models\Event> $events */
    public function payload(Child $child, Collection $events): array
    {
        $attendance = AttendanceRecord::query()->where('code', $child->code)->first();

        return [
            'child_id' => $child->code,
            'data_source' => 'train_data',
            'case_priority' => [
                'predicted_priority' => $child->predicted_priority,
                'probability_high' => $this->roundNullable($child->probability_high),
                'probability_medium' => $this->roundNullable($child->probability_medium),
                'probability_low' => $this->roundNullable($child->probability_low),
                'local_score' => $child->train_score ?? (int) $events->sum('score'),
                'risk_sources' => (int) $child->risk_sources,
                'distinct_event_types' => (int) $child->distinct_event_types,
                'active_social_case' => (int) (bool) $child->active_social_case,
            ],
            'event_summary' => [
                'total_events' => (int) $events->count(),
                'events_by_source' => [
                    'school' => (int) ($events->where('event_source', 'school')->count()),
                    'medical' => (int) ($events->where('event_source', 'medical')->count()),
                    'police' => (int) ($events->where('event_source', 'police')->count()),
                ],
                'top_event_types' => $events
                    ->groupBy('event_type')
                    ->map(fn (Collection $group, string $eventType) => [
                        'event_type' => $eventType,
                        'event_count' => $group->count(),
                    ])
                    ->filter(fn (array $item) => $item['event_count'] > 0)
                    ->sortByDesc('event_count')
                    ->take(5)
                    ->values()
                    ->all(),
                'important_events' => $events
                    ->sortByDesc('score')
                    ->take(5)
                    ->map(fn ($event) => [
                        'event_source' => $event->event_source,
                        'event_type' => $event->event_type,
                        'event_name' => $event->event_name,
                        'event_date' => $event->event_date?->toDateString(),
                        'score' => (int) $event->score,
                    ])
                    ->values()
                    ->all(),
            ],
            'attendance_anomaly' => $attendance ? [
                'enabled' => true,
                'anomaly_flag' => (int) (bool) $attendance->anomaly_flag,
                'anomaly_priority_score' => $this->roundNullable($attendance->anomaly_priority_score),
                'anomaly_rank' => $attendance->anomaly_rank ? (int) $attendance->anomaly_rank : null,
                'absent_days' => (int) $attendance->absent_days,
                'unexcused_absent_days' => (int) $attendance->unexcused_absent_days,
                'late_days' => (int) $attendance->late_days,
                'attendance_rate' => $this->roundNullable($attendance->attendance_rate),
                'absence_days_last_14d' => (int) $attendance->absence_days_last_14d,
                'absence_days_last_30d' => (int) $attendance->absence_days_last_30d,
                'max_consecutive_absent_days' => (int) $attendance->max_consecutive_absent_days,
                'monday_friday_absences' => (int) $attendance->monday_friday_absences,
            ] : ['enabled' => false],
        ];
    }

    /** @param Collection<int,\App\Models\Event> $events */
    private function fallback(Child $child, Collection $events): array
    {
        $concerns = collect(Child::PRIORITY_FEATURES)
            ->filter(fn ($f) => (bool) $child->{$f} && ! in_array($f, ['num_siblings'], true))
            ->map(fn ($f) => str($f)->headline()->toString())
            ->take(4)
            ->implode(', ');

        return [
            'summary' => 'Model priority: '.($child->predicted_priority ?? 'not scored')
                .($concerns ? '. Active risk indicators: '.$concerns.'.' : '.')
                .' Based on '.$events->count().' recorded events across school, medical and police sources.',
            'keyFactors' => [],
            'recommendations' => [
                "Assess the child's living conditions and safety",
                'Contact the school to clarify the situation.',
                "Evaluate the family's need for social support.",
            ],
            'disclaimer' => '',
            'payload' => $this->payload($child, $events),
        ];
    }

    private function roundNullable(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 4);
    }
}
