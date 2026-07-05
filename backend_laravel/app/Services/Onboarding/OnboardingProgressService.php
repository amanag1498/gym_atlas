<?php

namespace App\Services\Onboarding;

use App\Models\Gym;
use App\Models\User;

class OnboardingProgressService
{
    public function syncMemberProgress(User $user, ?int $step = null, bool $completed = false): User
    {
        $payload = [
            'member_onboarding_step' => max(1, min(8, $step ?? $user->member_onboarding_step ?? 1)),
        ];

        if ($completed) {
            $payload['member_onboarding_completed'] = true;
            $payload['member_onboarding_step'] = 8;
        } else {
            $payload['member_onboarding_completed'] = (bool) $user->member_onboarding_completed;
        }

        $user->forceFill($payload)->save();

        return $user->fresh();
    }

    public function syncTrainerProgress(User $user, ?int $step = null, bool $completed = false): User
    {
        $payload = [
            'trainer_onboarding_step' => max(1, min(7, $step ?? $user->trainer_onboarding_step ?? 1)),
        ];

        if ($completed) {
            $payload['trainer_onboarding_completed'] = true;
            $payload['trainer_onboarding_step'] = 7;
        } else {
            $payload['trainer_onboarding_completed'] = (bool) $user->trainer_onboarding_completed;
        }

        $user->forceFill($payload)->save();

        return $user->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function gymChecklist(Gym $gym): array
    {
        $gym->loadMissing(['branches', 'trainerProfiles', 'memberProfiles', 'membershipPlans']);

        $steps = [
            [
                'key' => 'gym_profile',
                'label' => 'Create gym profile',
                'completed' => filled($gym->name) && filled($gym->address_line) && ! empty($gym->timings),
            ],
            [
                'key' => 'first_branch',
                'label' => 'Add first branch',
                'completed' => $gym->branches->isNotEmpty(),
            ],
            [
                'key' => 'membership_plans',
                'label' => 'Add membership plans',
                'completed' => $gym->membershipPlans->isNotEmpty(),
            ],
            [
                'key' => 'trainers',
                'label' => 'Add trainers',
                'completed' => $gym->trainerProfiles->isNotEmpty(),
            ],
            [
                'key' => 'first_member',
                'label' => 'Add first member',
                'completed' => $gym->memberProfiles->isNotEmpty(),
            ],
            [
                'key' => 'public_listing',
                'label' => 'Enable public listing',
                'completed' => (bool) $gym->public_listing_enabled,
            ],
            [
                'key' => 'dashboard_ready',
                'label' => 'Dashboard ready',
                'completed' => false,
            ],
        ];

        $allCoreStepsDone = collect($steps)->take(6)->every(fn (array $step): bool => (bool) $step['completed']);
        $steps[6]['completed'] = $allCoreStepsDone;

        $completedCount = collect($steps)->where('completed', true)->count();

        return [
            'completed' => $completedCount === count($steps),
            'completed_count' => $completedCount,
            'total_steps' => count($steps),
            'progress_percent' => (int) round(($completedCount / count($steps)) * 100),
            'steps' => $steps,
        ];
    }

    public function syncGymProgress(Gym $gym): Gym
    {
        $checklist = $this->gymChecklist($gym);
        $gym->forceFill([
            'gym_onboarding_completed' => (bool) $checklist['completed'],
        ])->save();

        return $gym->fresh();
    }
}
