<?php

namespace App\Http\Resources\User;

use App\Support\Profiles\TrainerProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = TrainerProfilePresenter::present($this->resource, $this->relationLoaded('user') ? $this->user : null, [
            'include_client_count' => true,
        ]) ?? [];

        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'profile_photo_url' => $this->profile_photo_url,
            'bio' => $this->bio,
            'primary_specialization' => $summary['primary_specialization'] ?? null,
            'specializations' => $this->specializations ?? [],
            'experience_years' => $this->experience_years,
            'certifications' => $this->certifications ?? [],
            'languages' => $this->languages ?? [],
            'availability_notes' => $this->availability_notes,
            'availability_slots' => $summary['availability_slots'] ?? [],
            'assigned_gym' => $summary['assigned_gym'] ?? null,
            'assigned_branch' => $summary['assigned_branch'] ?? null,
            'client_count' => $summary['client_count'] ?? null,
            'client_count_placeholder' => $summary['client_count_placeholder'] ?? null,
            'rating_placeholder' => $summary['rating_placeholder'] ?? null,
            'transformation_photos_placeholder' => $summary['transformation_photos_placeholder'] ?? null,
            'programs_offered_placeholder' => $summary['programs_offered_placeholder'] ?? null,
            'profile_completion_percentage' => $summary['profile_completion_percentage'] ?? 0,
            'contact_action' => $summary['contact_action'] ?? null,
            'request_session_action' => $summary['request_session_action'] ?? null,
            'is_active' => $this->is_active,
            'verification_status' => $this->verification_status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
