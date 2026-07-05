<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Catalog\FitnessGoalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'assigned_trainer_user_id' => $this->assigned_trainer_user_id,
            'fitness_goal' => $this->fitness_goal,
            'fitness_goals' => FitnessGoalResource::collection($this->whenLoaded('fitnessGoals')),
            'gender' => $this->gender,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'experience_level' => $this->experience_level,
            'medical_notes' => $this->medical_notes,
            'injury_notes' => $this->injury_notes,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'membership_status' => $this->membership_status,
            'membership_expires_on' => $this->membership_expires_on?->toDateString(),
            'is_active' => $this->is_active,
            'engagement_score' => $this->getAttribute('engagement_score'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
