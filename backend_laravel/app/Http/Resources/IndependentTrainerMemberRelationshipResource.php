<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndependentTrainerMemberRelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trainerProfile = $this->relationLoaded('trainer') ? $this->trainer?->managedTrainerProfile : null;
        $accessActive = $this->status === 'active'
            && $trainerProfile !== null
            && $trainerProfile->is_active
            && $trainerProfile->status === 'active'
            && $trainerProfile->verification_status === 'verified';

        return [
            'id' => $this->id,
            'relationship_id' => $this->id,
            'source' => 'independent',
            'status' => $this->status,
            'access_active' => $accessActive,
            'trainer' => $this->whenLoaded('trainer', fn () => $this->trainer ? [
                'id' => $this->trainer->id,
                'name' => $this->trainer->name,
                'email' => $this->trainer->email,
                'avatar' => $this->trainer->avatar,
                'verification_status' => $this->trainer->managedTrainerProfile?->verification_status,
            ] : null),
            'member' => $this->whenLoaded('member', fn () => $this->member ? [
                'id' => $this->member->id,
                'name' => $this->member->name,
                'email' => $this->member->email,
                'avatar' => $this->member->avatar,
            ] : null),
            'invited_email' => $this->invited_email,
            'sharing_permissions' => $this->sharing_permissions ?? [],
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revocation_reason' => $this->revocation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
