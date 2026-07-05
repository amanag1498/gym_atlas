<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Support\Collection;

class AdminActivityFeedService
{
    public function __construct(
        private readonly AuditTimelineService $auditTimelineService,
    ) {}

    /**
     * @param  iterable<ActivityLog>  $logs
     * @return array{
     *     timeline: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, hint: string}>,
     *     rows: list<array<string, mixed>>,
     *     latest_label: string|null
     * }
     */
    public function build(iterable $logs): array
    {
        $logCollection = $logs instanceof Collection ? $logs->values() : collect($logs)->values();
        $timeline = $this->auditTimelineService->forActivityLogs($logCollection);

        $rows = $logCollection->map(function (ActivityLog $log, int $index) use ($timeline): array {
            $item = $timeline[$index] ?? [];

            return [
                'title' => $item['title'] ?? 'Audit event',
                'change_summary' => $item['change_summary'] ?? null,
                'reason' => $item['reason'] ?? null,
                'changed_by' => $item['changed_by'] ?? 'System',
                'changed_by_role' => $item['changed_by_role'] ?? 'System',
                'date' => $item['date'] ?? null,
                'event' => $log->event,
                'action' => $log->action,
                'gym_name' => $log->gym?->name,
                'branch_name' => $log->branch?->name,
                'ip_address' => $log->ip_address,
                'tone' => $item['tone'] ?? 'neutral',
            ];
        })->all();

        return [
            'timeline' => $timeline,
            'stats' => [
                [
                    'label' => 'Events',
                    'value' => (string) $logCollection->count(),
                    'hint' => 'Loaded audit rows',
                ],
                [
                    'label' => 'Actions',
                    'value' => (string) $logCollection->pluck('action')->filter()->unique()->count(),
                    'hint' => 'Unique action types',
                ],
                [
                    'label' => 'Gyms',
                    'value' => (string) $logCollection->pluck('gym_id')->filter()->unique()->count(),
                    'hint' => 'Impacted gyms',
                ],
                [
                    'label' => 'Branches',
                    'value' => (string) $logCollection->pluck('branch_id')->filter()->unique()->count(),
                    'hint' => 'Impacted branches',
                ],
            ],
            'rows' => $rows,
            'latest_label' => $logCollection->first()?->occurred_at?->diffForHumans(),
        ];
    }
}
