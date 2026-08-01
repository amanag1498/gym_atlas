<?php

namespace App\Http\Resources\PlatformAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndependentTrainerVerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trainer_user_id' => $this->user_id,
            'trainer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone ?? null,
                'is_active' => (bool) $this->user->is_active,
            ]),
            'independent' => $this->gym_id === null,
            'profile_photo_url' => $this->profile_photo_url,
            'bio' => $this->bio,
            'specialization' => $this->specialization,
            'specializations' => $this->specializations ?? [],
            'experience_years' => $this->experience_years,
            'certifications' => $this->certifications ?? [],
            'languages' => $this->languages ?? [],
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'verification' => [
                'status' => $this->verification_status,
                'reviewed_at' => $this->verification_reviewed_at?->toIso8601String(),
                'verified_at' => $this->verification_verified_at?->toIso8601String(),
                'rejection_reason' => $this->verification_rejection_reason,
                'notes' => $this->verification_review_notes,
                'reviewer' => $this->whenLoaded('verificationReviewer', fn () => $this->verificationReviewer ? [
                    'id' => $this->verificationReviewer->id,
                    'name' => $this->verificationReviewer->name,
                    'email' => $this->verificationReviewer->email,
                ] : null),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
