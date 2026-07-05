<?php

namespace App\Http\Resources\Member;

use App\Http\Resources\Catalog\FitnessGoalResource;
use App\Models\FitnessGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberAppProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->memberProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'photo' => $this->avatar,
            'auth_provider' => $this->auth_provider,
            'google_id' => $this->google_id,
            'member_onboarding_completed' => (bool) $this->member_onboarding_completed,
            'member_onboarding_step' => (int) ($this->member_onboarding_step ?? 1),
            'fitness_goal' => $profile?->fitness_goal,
            'fitness_goals' => $profile?->fitnessGoals
                ? FitnessGoalResource::collection($profile->fitnessGoals)->resolve($request)
                : [],
            'available_fitness_goals' => FitnessGoalResource::collection(
                FitnessGoal::query()->active()->ordered()->get()
            )->resolve($request),
            'gender' => $profile?->gender,
            'height_cm' => $profile?->height_cm !== null ? (float) $profile->height_cm : null,
            'weight_kg' => $profile?->weight_kg !== null ? (float) $profile->weight_kg : null,
            'experience_level' => $profile?->experience_level,
            'injuries_limitations' => $profile?->injury_notes,
            'medical_notes' => $profile?->medical_notes,
            'current_gym' => $profile?->gym ? [
                'id' => $profile->gym->id,
                'name' => $profile->gym->name,
                'slug' => $profile->gym->slug,
                'logo_url' => $profile->gym->logo_url,
            ] : null,
            'current_branch' => $profile?->branch ? [
                'id' => $profile->branch->id,
                'name' => $profile->branch->name,
                'slug' => $profile->branch->slug,
                'address_line' => $profile->branch->address_line,
                'city' => $profile->branch->city,
            ] : null,
            'assigned_trainer' => $profile?->assignedTrainer ? [
                'id' => $profile->assignedTrainer->id,
                'name' => $profile->assignedTrainer->name,
                'photo' => $profile->assignedTrainer->avatar,
                'profile_photo_url' => $profile->assignedTrainer->managedTrainerProfile?->profile_photo_url,
                'bio' => $profile->assignedTrainer->managedTrainerProfile?->bio,
            ] : null,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
        ];
    }
}
