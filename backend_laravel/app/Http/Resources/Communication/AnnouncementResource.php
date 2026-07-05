<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'created_by_user_id' => $this->created_by_user_id,
            'audience_type' => $this->audience_type,
            'title' => $this->title,
            'message' => $this->message,
            'is_platform_wide' => $this->is_platform_wide,
            'send_at' => $this->send_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'recipient_count' => $this->whenCounted('recipients'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
