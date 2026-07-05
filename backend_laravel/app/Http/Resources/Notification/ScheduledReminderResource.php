<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'member_membership_id' => $this->member_membership_id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'payload' => $this->payload,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'status' => $this->status,
        ];
    }
}
