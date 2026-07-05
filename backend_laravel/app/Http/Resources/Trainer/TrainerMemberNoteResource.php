<?php

namespace App\Http\Resources\Trainer;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerMemberNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainer_id' => $this->trainer_id,
            'member_id' => $this->member_id,
            'note' => $this->note,
            'visibility' => $this->visibility,
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'member' => UserResource::make($this->whenLoaded('member')),
            'trainer' => UserResource::make($this->whenLoaded('trainer')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
