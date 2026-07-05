<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $event,
        string $action,
        ?Request $request = null,
        ?Model $subject = null,
        ?Gym $gym = null,
        ?Branch $branch = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $context = [],
    ): ActivityLog {
        $user = $request?->user();

        return ActivityLog::query()->create([
            'actor_user_id' => $user?->getKey(),
            'user_id' => $user?->getKey(),
            'gym_id' => $gym?->getKey(),
            'branch_id' => $branch?->getKey(),
            'event' => $event,
            'action' => $action,
            'actor_role' => $user?->active_role,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
