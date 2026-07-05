<?php

namespace App\Http\Resources\Member;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberGymInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'gym' => $this->whenLoaded('gym', fn () => [
                'id' => $this->gym?->id,
                'name' => $this->gym?->name,
                'city' => $this->gym?->city,
                'logo_url' => $this->gym?->logo_url,
            ]),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch?->id,
                'name' => $this->branch?->name,
            ]),
            'assigned_trainer_user_id' => $this->assigned_trainer_user_id,
            'assigned_trainer' => $this->whenLoaded('assignedTrainer', fn () => $this->assignedTrainer ? [
                'id' => $this->assignedTrainer->id,
                'name' => $this->assignedTrainer->name,
                'email' => $this->assignedTrainer->email,
                'avatar' => $this->assignedTrainer->avatar,
            ] : null),
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
