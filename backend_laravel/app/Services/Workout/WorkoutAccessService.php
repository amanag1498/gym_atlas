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
use App\Services\Member\MemberAppService;
use App\Services\Members\GymMemberAccessService;
use App\Services\Trainer\IndependentCoachingAccessService;
use Illuminate\Validation\ValidationException;

class WorkoutAccessService
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
        private readonly GymMemberAccessService $gymMemberAccessService,
        private readonly MemberAppService $memberAppService,
    ) {}

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

        $this->gymMemberAccessService->assertAccessible($profile);

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

        if ($template->gym_id === null && (int) $template->created_by_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'workout_template_id' => ['You do not have access to this workout template.'],
            ]);
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
        if ($plan->independent_trainer_member_relationship_id === null && $plan->status !== 'active') {
            throw ValidationException::withMessages([
                'workout_plan_id' => ['This gym workout plan is historical and is no longer available as a current assignment.'],
            ]);
        }

        if ($actor->active_role === RoleName::Member->value) {
            $this->assertMemberSelfAccess($actor, $plan->member_id);
            if ((int) $plan->created_by_user_id === (int) $actor->id
                && $plan->trainer_id === null
                && $plan->is_member_editable) {
                return;
            }
            if ($plan->independent_trainer_member_relationship_id !== null) {
                $this->independentCoachingAccessService->resolveForMember(
                    $actor,
                    (int) $plan->independent_trainer_member_relationship_id,
                    'workouts',
                );

                return;
            }
            $profile = $this->memberAppService->memberProfileFor($actor);
            if (! $profile || (int) $profile->gym_id !== (int) $plan->gym_id || ($plan->branch_id && (int) $profile->branch_id !== (int) $plan->branch_id)) {
                throw ValidationException::withMessages(['workout_plan_id' => ['This workout plan is not available in your current gym space.']]);
            }
            $this->gymMemberAccessService->assertAccessible($profile);

            return;
        }

        if ($actor->active_role === RoleName::Trainer->value) {
            if ($plan->independent_trainer_member_relationship_id !== null) {
                if ((int) $plan->trainer_id !== (int) $actor->id) {
                    throw ValidationException::withMessages(['workout_plan_id' => ['You do not have access to this workout plan.']]);
                }
                $this->independentCoachingAccessService->resolveActiveRelationship(
                    $actor,
                    $plan->member,
                    (int) $plan->independent_trainer_member_relationship_id,
                    'workouts',
                );

                return;
            }
            $actor->loadMissing('managedTrainerProfile');
            $profile = $actor->managedTrainerProfile;
            if ((int) $plan->trainer_id !== (int) $actor->id || ! $profile || (int) $plan->gym_id !== (int) $profile->gym_id || ($profile->branch_id && (int) $plan->branch_id !== (int) $profile->branch_id)) {
                throw ValidationException::withMessages([
                    'workout_plan_id' => ['You do not have access to this workout plan.'],
                ]);
            }
            $memberProfile = MemberProfile::query()
                ->where('user_id', $plan->member_id)
                ->where('gym_id', $plan->gym_id)
                ->where('assigned_trainer_user_id', $actor->id)
                ->firstOrFail();
            $this->gymMemberAccessService->assertAccessible($memberProfile);

            return;
        }

        $memberProfile = MemberProfile::query()
            ->where('user_id', $plan->member_id)
            ->where('gym_id', $plan->gym_id)
            ->first();
        if (! $memberProfile) {
            throw ValidationException::withMessages(['workout_plan_id' => ['This workout plan is not in an active gym member scope.']]);
        }
        $this->gymMemberAccessService->assertAccessible($memberProfile);

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

            if ($session->gym_id !== null) {
                $profile = $this->memberAppService->memberProfileFor($actor);
                if (! $profile
                    || (int) $profile->gym_id !== (int) $session->gym_id
                    || ($session->branch_id !== null && (int) $profile->branch_id !== (int) $session->branch_id)) {
                    throw ValidationException::withMessages([
                        'workout_session_id' => ['This workout session is not available in your selected gym space.'],
                    ]);
                }
                $this->gymMemberAccessService->assertAccessible($profile);
            }

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

        $memberProfile = MemberProfile::query()
            ->where('user_id', $session->member_id)
            ->where('gym_id', $session->gym_id)
            ->firstOrFail();
        $this->gymMemberAccessService->assertAccessible($memberProfile);
    }

    public function assertSessionReadAccess(User $actor, WorkoutSession $session): void
    {
        if ($actor->active_role === RoleName::Member->value) {
            $this->assertMemberSelfAccess($actor, $session->member_id);

            return;
        }

        $this->assertSessionAccess($actor, $session);
    }
}
