<?php

namespace App\Http\Resources\Audit;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'action' => $this->action,
            'actor_role' => $this->actor_role,
            'actor' => UserResource::make($this->whenLoaded('actor')),
            'context' => $this->context ?? [],
            'old_values' => $this->old_values ?? [],
            'new_values' => $this->new_values ?? [],
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
