<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when(isset($this->phone), $this->phone),
            'google_id' => $this->google_id,
            'avatar' => $this->avatar,
            'auth_provider' => $this->auth_provider,
            'is_active' => (bool) $this->is_active,
            'active_role' => $this->active_role,
            'member_onboarding_completed' => (bool) $this->member_onboarding_completed,
            'member_onboarding_step' => (int) ($this->member_onboarding_step ?? 1),
            'trainer_onboarding_completed' => (bool) $this->trainer_onboarding_completed,
            'trainer_onboarding_step' => (int) ($this->trainer_onboarding_step ?? 1),
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'gyms' => \App\Http\Resources\Gym\GymResource::collection($this->whenLoaded('gyms')),
            'branches' => \App\Http\Resources\Gym\BranchResource::collection($this->whenLoaded('branches')),
            'trainer_profile' => $this->when(
                $this->relationLoaded('managedTrainerProfile') && $this->managedTrainerProfile !== null,
                fn () => TrainerProfileResource::make($this->managedTrainerProfile),
            ),
            'member_profile' => $this->when(
                $this->relationLoaded('memberProfile') && $this->memberProfile !== null,
                fn () => MemberProfileResource::make($this->memberProfile),
            ),
            'owned_gyms' => \App\Http\Resources\Gym\GymResource::collection($this->whenLoaded('ownedGyms')),
            'owned_gyms_count' => $this->whenCounted('ownedGyms'),
            'staff_assignments' => $this->whenLoaded('staffAssignments', fn () => $this->staffAssignments->map(fn ($assignment): array => [
                'id' => $assignment->id,
                'gym_id' => $assignment->gym_id,
                'gym_name' => $assignment->gym?->name,
                'branch_id' => $assignment->branch_id,
                'branch_name' => $assignment->branch?->name,
                'role_name' => $assignment->role_name,
                'status' => $assignment->status,
                'permissions' => $assignment->permissions,
                'custom_permissions' => $assignment->custom_permissions,
            ])->values()->all()),
            'activity_logs' => $this->whenLoaded('activityLogs', fn () => $this->activityLogs->map(fn ($log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'action' => $log->action,
                'gym_name' => $log->gym?->name,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])->values()->all()),
            'engagement_score' => $this->getAttribute('engagement_score'),
            'current_membership_id' => $this->getAttribute('current_membership_id'),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
