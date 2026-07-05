<?php

namespace App\Http\Resources\Audit;

use App\Services\Platform\PlatformAuditLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PlatformAuditLogService $auditLogService */
        $auditLogService = app(PlatformAuditLogService::class);

        return [
            'id' => $this->id,
            'action' => $this->action ?: $this->event,
            'event' => $this->event,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ] : null,
            'actor_role' => $this->actor_role,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'gym' => $this->gym ? [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null,
            'old_values' => $auditLogService->sanitizeValue($this->old_values ?? []),
            'new_values' => $auditLogService->sanitizeValue($this->new_values ?? []),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
