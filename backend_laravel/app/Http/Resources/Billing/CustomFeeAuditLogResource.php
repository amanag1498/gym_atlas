<?php

namespace App\Http\Resources\Billing;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomFeeAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member_membership_id' => $this->member_membership_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changed_by' => $this->changed_by,
            'changed_by_user' => UserResource::make($this->whenLoaded('changer')),
            'reason' => $this->reason,
            'changed_at' => $this->changed_at?->toIso8601String(),
        ];
    }
}
