<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'notification_type' => $this->notification_type,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
