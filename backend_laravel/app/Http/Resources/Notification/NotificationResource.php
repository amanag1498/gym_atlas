<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'announcement_id' => $this->announcement_id,
            'member_membership_id' => $this->member_membership_id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_by_user_id' => $this->created_by_user_id,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
