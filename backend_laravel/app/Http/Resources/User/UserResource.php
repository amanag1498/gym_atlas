<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Gym\BranchResource;
use App\Http\Resources\Gym\GymResource;
use App\Services\Privacy\ConsentService;
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
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
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
            'consents' => $this->whenLoaded('consentRecords', function (): array {
                $records = $this->consentRecords
                    ->sortByDesc('id')
                    ->unique('purpose')
                    ->keyBy('purpose');

                return collect(ConsentService::PURPOSES)->map(function (array $definition, string $purpose) use ($records): array {
                    $record = $records->get($purpose);
                    $granted = $record !== null && $record->consented_at !== null && $record->withdrawn_at === null;

                    return [
                        'purpose' => $purpose,
                        'title' => $definition['title'],
                        'required' => $definition['required'],
                        'policy_version' => $record?->policy_version ?? ConsentService::POLICY_VERSION,
                        'granted' => $granted,
                        'consented_at' => $granted ? $record->consented_at?->toIso8601String() : null,
                        'withdrawn_at' => $record?->withdrawn_at?->toIso8601String(),
                    ];
                })->values()->all();
            }),
            'whatsapp_consents' => $this->whenLoaded('whatsappConsents', fn () => $this->whatsappConsents->map(fn ($consent): array => [
                'gym_id' => $consent->gym_id,
                'purpose' => $consent->purpose,
                'status' => $consent->status,
                'granted' => $consent->granted_at !== null && $consent->revoked_at === null && $consent->status === 'granted',
                'granted_at' => $consent->granted_at?->toIso8601String(),
                'revoked_at' => $consent->revoked_at?->toIso8601String(),
            ])->values()->all()),
            'gyms' => GymResource::collection($this->whenLoaded('gyms')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'trainer_profile' => $this->when(
                $this->relationLoaded('managedTrainerProfile') && $this->managedTrainerProfile !== null,
                fn () => TrainerProfileResource::make($this->managedTrainerProfile),
            ),
            'member_profile' => $this->when(
                $this->relationLoaded('memberProfile') && $this->memberProfile !== null,
                fn () => MemberProfileResource::make($this->memberProfile),
            ),
            'member_profiles' => MemberProfileResource::collection($this->whenLoaded('memberProfiles')),
            'owned_gyms' => GymResource::collection($this->whenLoaded('ownedGyms')),
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
