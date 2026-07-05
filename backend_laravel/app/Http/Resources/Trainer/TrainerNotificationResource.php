<?php

namespace App\Http\Resources\Trainer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
