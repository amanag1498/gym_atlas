<?php

namespace App\Http\Resources\Attendance;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_id' => $this->member_id,
            'checked_in_by' => $this->checked_in_by,
            'check_in_method' => $this->check_in_method,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'notes' => $this->notes,
            'source_device' => $this->source_device,
            'biometric_device_id' => $this->biometric_device_id,
            'biometric_device_event_id' => $this->biometric_device_event_id,
            'occurred_at_device' => $this->occurred_at_device?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'gym' => $this->whenLoaded('gym', fn (): array => [
                'id' => $this->gym?->id,
                'name' => $this->gym?->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn (): array => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'member' => UserResource::make($this->whenLoaded('member')),
            'checked_in_by_user' => UserResource::make($this->whenLoaded('checkedInByUser')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
