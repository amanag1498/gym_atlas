<?php

namespace App\Services\Workout;

use App\Enums\RoleName;
use App\Models\Exercise;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Validation\ValidationException;

class WorkoutAccessService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
    ) {
    }

    public function assertTrainerCanAccessMember(User $trainer, User $member): MemberProfile
    {
        $profile = MemberProfile::query()
            ->where('user_id', $member->id)
            ->where('assigned_trainer_user_id', $trainer->id)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'member_ids' => ['You can assign workouts only to your assigned members.'],
            ]);
        }

        return $profile;
    }

    public function assertMemberSelfAccess(User $actor, int $memberId): void
    {
        if ($actor->active_role !== RoleName::Member->value || (int) $actor->id !== $memberId) {
            throw ValidationException::withMessages([
                'member_id' => ['You can access only your own workout data.'],
            ]);
        }
    }

    public function assertExerciseAccess(User $actor, Exercise $exercise): void
    {
        if ($exercise->is_global) {
            return;
        }

        if (! $exercise->gym_id || ! $this->scopeResolver->canAccessGym($actor, $exercise->gym_id)) {
            throw ValidationException::withMessages([
                'exercise_id' => ['You do not have access to this exercise.'],
            ]);
        }

        if ($exercise->branch_id && ! $this->scopeResolver->canAccessBranch($actor, $exercise->branch_id)) {
            throw ValidationException::withMessages([
                'exercise_id' => ['You do not have access to this exercise.'],
            ]);
        }
    }

    public function assertTemplateAccess(User $actor, WorkoutTemplate $template): void
    {
        if ($template->is_public_catalog) {
            return;
        }

        if ($template->gym_id && ! $this->scopeResolver->canAccessGym($actor, $template->gym_id)) {
            throw ValidationException::withMessages([
                'workout_template_id' => ['You do not have access to this workout template.'],
            ]);
        }

        if ($template->branch_id && ! $this->scopeResolver->canAccessBranch($actor, $template->branch_id)) {
            throw ValidationException::withMessages([
                'workout_template_id' => ['You do not have access to this workout template.'],
            ]);
        }
    }

    public function assertPlanAccess(User $actor, WorkoutPlan $plan): void
    {
        if ($actor->active_role === RoleName::Member->value) {
            $this->assertMemberSelfAccess($actor, $plan->member_id);

            return;
        }

        if ($actor->active_role === RoleName::Trainer->value) {
            if ((int) $plan->trainer_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'workout_plan_id' => ['You do not have access to this workout plan.'],
                ]);
            }

            return;
        }

        if ((! $plan->gym_id || ! $this->scopeResolver->canAccessGym($actor, $plan->gym_id))
            || (! $plan->branch_id || ! $this->scopeResolver->canAccessBranch($actor, $plan->branch_id))) {
            throw ValidationException::withMessages([
                'workout_plan_id' => ['You do not have access to this workout plan.'],
            ]);
        }
    }

    public function assertSessionAccess(User $actor, WorkoutSession $session): void
    {
        if ($actor->active_role === RoleName::Member->value) {
            $this->assertMemberSelfAccess($actor, $session->member_id);

            return;
        }

        if ($actor->active_role === RoleName::Trainer->value) {
            if ($session->trainer_id !== null && (int) $session->trainer_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'workout_session_id' => ['You do not have access to this workout session.'],
                ]);
            }

            $this->assertTrainerCanAccessMember($actor, $session->member);

            return;
        }

        if (! $this->scopeResolver->canAccessGym($actor, $session->gym_id) || ! $this->scopeResolver->canAccessBranch($actor, $session->branch_id)) {
            throw ValidationException::withMessages([
                'workout_session_id' => ['You do not have access to this workout session.'],
            ]);
        }
    }
}
