<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndependentTrainerMemberInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trainer = $this->relationLoaded('trainer') ? $this->trainer : null;
        $trainerProfile = $trainer?->managedTrainerProfile;
        $trainerEligible = $trainer?->is_active
            && $trainerProfile !== null
            && $trainerProfile->gym_id === null
            && $trainerProfile->branch_id === null
            && $trainerProfile->is_active
            && $trainerProfile->status === 'active'
            && $trainerProfile->verification_status === 'verified';

        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'source' => 'independent',
            'status' => $this->status,
            'actionable' => $this->status === 'pending'
                && $this->expires_at?->isFuture() === true
                && $trainerEligible,
            'trainer' => $this->whenLoaded('trainer', fn () => $this->trainer ? [
                'id' => $this->trainer->id,
                'name' => $this->trainer->name,
                'email' => $this->trainer->email,
                'avatar' => $this->trainer->avatar,
                'verification_status' => $this->trainer->managedTrainerProfile?->verification_status,
            ] : null),
            'invited_user_id' => $this->invited_user_id,
            'invited_name' => $this->invited_name,
            'invited_email' => $this->invited_email,
            'approval_channel' => $this->invited_user_id ? 'app' : 'email',
            'message' => data_get($this->payload, 'message'),
            'sharing_permissions' => data_get($this->payload, 'sharing_permissions', []),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
