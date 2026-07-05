<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanDay;
use App\Models\WorkoutPlanExercise;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Facades\DB;

class WorkoutPlanService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Support\Collection<int, WorkoutPlan>
     */
    public function createPlans(User $trainer, array $payload)
    {
        return DB::transaction(function () use ($trainer, $payload) {
            $plans = collect();

            foreach ($payload['member_ids'] as $memberId) {
                $plan = WorkoutPlan::query()->create([
                    'gym_id' => $payload['gym_id'],
                    'branch_id' => $payload['branch_id'],
                    'member_id' => $memberId,
                    'trainer_id' => $trainer->id,
                    'created_by_user_id' => $trainer->id,
                    'source_workout_book_id' => $payload['source_workout_book_id'] ?? null,
                    'plan_origin' => $payload['plan_origin'] ?? 'trainer_assigned',
                    'is_member_editable' => (bool) ($payload['is_member_editable'] ?? false),
                    'workout_template_id' => $payload['workout_template_id'] ?? null,
                    'name' => $payload['name'],
                    'goal' => $payload['goal'] ?? null,
                    'difficulty' => $payload['difficulty'] ?? null,
                    'duration_weeks' => $payload['duration_weeks'],
                    'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                    'equipment_profile' => $payload['equipment_profile'] ?? null,
                    'weekly_schedule' => $payload['weekly_schedule'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'status' => $payload['status'] ?? 'active',
                    'assigned_at' => now(),
                    'starts_on' => $payload['starts_on'] ?? null,
                    'ends_on' => $payload['ends_on'] ?? null,
                ]);

                $this->syncPlanDays($plan, $payload['days'] ?? []);
                $plans->push($plan->load('days.exercises.exercise'));
            }

            return $plans;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePlan(WorkoutPlan $plan, array $payload): WorkoutPlan
    {
        return DB::transaction(function () use ($plan, $payload) {
            $plan->update([
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $plan->estimated_session_minutes,
                'equipment_profile' => $payload['equipment_profile'] ?? $plan->equipment_profile,
                'weekly_schedule' => $payload['weekly_schedule'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? $plan->status,
                'starts_on' => $payload['starts_on'] ?? $plan->starts_on,
                'ends_on' => $payload['ends_on'] ?? $plan->ends_on,
            ]);

            $plan->days()->delete();
            $this->syncPlanDays($plan, $payload['days'] ?? []);

            return $plan->fresh('days.exercises.exercise');
        });
    }

    public function createTemplateFromPayload(User $trainer, array $payload): WorkoutTemplate
    {
        return DB::transaction(function () use ($trainer, $payload) {
            $template = WorkoutTemplate::query()->create([
                'gym_id' => $payload['gym_id'] ?? null,
                'branch_id' => $payload['branch_id'] ?? null,
                'workout_book_id' => $payload['workout_book_id'] ?? null,
                'created_by_user_id' => $trainer->id,
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'program_type' => $payload['program_type'] ?? null,
                'equipment_profile' => $payload['equipment_profile'] ?? null,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                'weekly_schedule' => $payload['weekly_schedule'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'is_public_catalog' => (bool) ($payload['is_public_catalog'] ?? false),
            ]);

            foreach ($payload['days'] ?? [] as $dayPayload) {
                $day = $template->days()->create([
                    'day_number' => $dayPayload['day_number'],
                    'label' => $dayPayload['label'] ?? null,
                    'focus' => $dayPayload['focus'] ?? null,
                    'notes' => $dayPayload['notes'] ?? null,
                ]);

                foreach ($dayPayload['exercises'] ?? [] as $exercisePayload) {
                    $day->exercises()->create([
                        'exercise_id' => $exercisePayload['exercise_id'],
                        'sort_order' => $exercisePayload['sort_order'] ?? 1,
                        'sets' => $exercisePayload['sets'],
                        'reps' => $exercisePayload['reps'] ?? null,
                        'target_weight' => $exercisePayload['target_weight'] ?? null,
                        'rest_seconds' => $exercisePayload['rest_seconds'] ?? null,
                        'notes' => $exercisePayload['notes'] ?? null,
                    ]);
                }
            }

            return $template->load('days.exercises.exercise');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateTemplate(WorkoutTemplate $template, array $payload): WorkoutTemplate
    {
        return DB::transaction(function () use ($template, $payload) {
            $template->update([
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'program_type' => $payload['program_type'] ?? $template->program_type,
                'equipment_profile' => $payload['equipment_profile'] ?? $template->equipment_profile,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $template->estimated_session_minutes,
                'weekly_schedule' => $payload['weekly_schedule'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? $template->status,
            ]);

            $template->days()->delete();

            foreach ($payload['days'] ?? [] as $dayPayload) {
                $day = $template->days()->create([
                    'day_number' => $dayPayload['day_number'],
                    'label' => $dayPayload['label'] ?? null,
                    'focus' => $dayPayload['focus'] ?? null,
                    'notes' => $dayPayload['notes'] ?? null,
                ]);

                foreach ($dayPayload['exercises'] ?? [] as $exercisePayload) {
                    $day->exercises()->create([
                        'exercise_id' => $exercisePayload['exercise_id'],
                        'sort_order' => $exercisePayload['sort_order'] ?? 1,
                        'sets' => $exercisePayload['sets'],
                        'reps' => $exercisePayload['reps'] ?? null,
                        'target_weight' => $exercisePayload['target_weight'] ?? null,
                        'rest_seconds' => $exercisePayload['rest_seconds'] ?? null,
                        'notes' => $exercisePayload['notes'] ?? null,
                    ]);
                }
            }

            return $template->fresh('days.exercises.exercise');
        });
    }

    public function assignTemplateToMembers(User $trainer, WorkoutTemplate $template, array $payload)
    {
        $planPayload = [
            'gym_id' => $payload['gym_id'],
            'branch_id' => $payload['branch_id'],
            'member_ids' => $payload['member_ids'],
            'workout_template_id' => $template->id,
            'name' => $payload['name'] ?? $template->name,
            'goal' => $payload['goal'] ?? $template->goal,
            'difficulty' => $payload['difficulty'] ?? $template->difficulty,
            'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $template->estimated_session_minutes,
            'equipment_profile' => $payload['equipment_profile'] ?? $template->equipment_profile,
            'duration_weeks' => $payload['duration_weeks'] ?? $template->duration_weeks,
            'weekly_schedule' => $payload['weekly_schedule'] ?? $template->weekly_schedule,
            'notes' => $payload['notes'] ?? $template->notes,
            'status' => $payload['status'] ?? 'active',
            'source_workout_book_id' => $template->workout_book_id,
            'plan_origin' => $payload['plan_origin'] ?? ($template->is_public_catalog ? 'catalog_adopted' : 'trainer_assigned'),
            'is_member_editable' => (bool) ($payload['is_member_editable'] ?? false),
            'starts_on' => $payload['starts_on'] ?? null,
            'ends_on' => $payload['ends_on'] ?? null,
            'days' => $template->days->map(fn ($day) => [
                'day_number' => $day->day_number,
                'label' => $day->label,
                'focus' => $day->focus,
                'notes' => $day->notes,
                'exercises' => $day->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->exercise_id,
                    'sort_order' => $exercise->sort_order,
                    'sets' => $exercise->sets,
                    'reps' => $exercise->reps,
                    'target_weight' => $exercise->target_weight,
                    'rest_seconds' => $exercise->rest_seconds,
                    'notes' => $exercise->notes,
                ])->values()->all(),
            ])->values()->all(),
        ];

        return $this->createPlans($trainer, $planPayload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createMemberPlan(User $member, array $payload): WorkoutPlan
    {
        return DB::transaction(function () use ($member, $payload) {
            $member->loadMissing('memberProfile');
            $profile = $member->memberProfile;

            $plan = WorkoutPlan::query()->create([
                'gym_id' => $profile?->gym_id,
                'branch_id' => $profile?->branch_id,
                'member_id' => $member->id,
                'trainer_id' => null,
                'created_by_user_id' => $member->id,
                'source_workout_book_id' => $payload['source_workout_book_id'] ?? null,
                'plan_origin' => $payload['plan_origin'] ?? 'member_custom',
                'is_member_editable' => true,
                'workout_template_id' => $payload['workout_template_id'] ?? null,
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                'equipment_profile' => $payload['equipment_profile'] ?? null,
                'weekly_schedule' => $payload['weekly_schedule'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'status' => $payload['status'] ?? 'active',
                'assigned_at' => now(),
                'starts_on' => $payload['starts_on'] ?? null,
                'ends_on' => $payload['ends_on'] ?? null,
            ]);

            $this->syncPlanDays($plan, $payload['days'] ?? []);

            return $plan->load(['days.exercises.exercise', 'template', 'sourceWorkoutBook']);
        });
    }

    public function adoptTemplateForMember(User $member, WorkoutTemplate $template, array $payload): WorkoutPlan
    {
        $template->loadMissing('days.exercises');

        return $this->createMemberPlan($member, [
            'workout_template_id' => $template->id,
            'source_workout_book_id' => $template->workout_book_id,
            'plan_origin' => 'catalog_adopted',
            'name' => $payload['name'] ?? $template->name,
            'goal' => $payload['goal'] ?? $template->goal,
            'difficulty' => $payload['difficulty'] ?? $template->difficulty,
            'duration_weeks' => $payload['duration_weeks'] ?? $template->duration_weeks,
            'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $template->estimated_session_minutes,
            'equipment_profile' => $payload['equipment_profile'] ?? $template->equipment_profile,
            'weekly_schedule' => $payload['weekly_schedule'] ?? $template->weekly_schedule,
            'notes' => $payload['notes'] ?? $template->notes,
            'status' => $payload['status'] ?? 'active',
            'starts_on' => $payload['starts_on'] ?? null,
            'ends_on' => $payload['ends_on'] ?? null,
            'days' => $template->days->map(fn ($day) => [
                'day_number' => $day->day_number,
                'label' => $day->label,
                'focus' => $day->focus,
                'notes' => $day->notes,
                'exercises' => $day->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->exercise_id,
                    'sort_order' => $exercise->sort_order,
                    'sets' => $exercise->sets,
                    'reps' => $exercise->reps,
                    'target_weight' => $exercise->target_weight,
                    'rest_seconds' => $exercise->rest_seconds,
                    'notes' => $exercise->notes,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function duplicatePlanForMember(User $member, WorkoutPlan $plan, ?string $name = null): WorkoutPlan
    {
        $plan->loadMissing('days.exercises');

        return $this->createMemberPlan($member, [
            'workout_template_id' => $plan->workout_template_id,
            'source_workout_book_id' => $plan->source_workout_book_id,
            'plan_origin' => 'member_custom',
            'name' => $name ?: sprintf('%s Copy', $plan->name),
            'goal' => $plan->goal,
            'difficulty' => $plan->difficulty,
            'duration_weeks' => $plan->duration_weeks,
            'estimated_session_minutes' => $plan->estimated_session_minutes,
            'equipment_profile' => $plan->equipment_profile,
            'weekly_schedule' => $plan->weekly_schedule,
            'notes' => $plan->notes,
            'status' => 'active',
            'days' => $plan->days->map(fn ($day) => [
                'day_number' => $day->day_number,
                'label' => $day->label,
                'focus' => $day->focus,
                'notes' => $day->notes,
                'exercises' => $day->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->exercise_id,
                    'sort_order' => $exercise->sort_order,
                    'sets' => $exercise->sets,
                    'reps' => $exercise->reps,
                    'target_weight' => $exercise->target_weight,
                    'rest_seconds' => $exercise->rest_seconds,
                    'notes' => $exercise->notes,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    private function syncPlanDays(WorkoutPlan $plan, array $days): void
    {
        foreach ($days as $dayPayload) {
            $day = WorkoutPlanDay::query()->create([
                'workout_plan_id' => $plan->id,
                'day_number' => $dayPayload['day_number'],
                'label' => $dayPayload['label'] ?? null,
                'focus' => $dayPayload['focus'] ?? null,
                'notes' => $dayPayload['notes'] ?? null,
            ]);

            foreach ($dayPayload['exercises'] ?? [] as $exercisePayload) {
                WorkoutPlanExercise::query()->create([
                    'workout_plan_day_id' => $day->id,
                    'exercise_id' => $exercisePayload['exercise_id'],
                    'sort_order' => $exercisePayload['sort_order'] ?? 1,
                    'sets' => $exercisePayload['sets'],
                    'reps' => $exercisePayload['reps'] ?? null,
                    'target_weight' => $exercisePayload['target_weight'] ?? null,
                    'rest_seconds' => $exercisePayload['rest_seconds'] ?? null,
                    'notes' => $exercisePayload['notes'] ?? null,
                ]);
            }
        }
    }
}
