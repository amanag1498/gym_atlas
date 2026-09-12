<?php

namespace App\Services\Workout;

use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutPlanDay;
use App\Models\WorkoutPlanExercise;
use App\Models\WorkoutTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutPlanService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, WorkoutPlan>
     */
    public function createPlans(User $trainer, array $payload)
    {
        $this->assertExecutionModes($payload['days'] ?? []);
        $this->assertCoachingConfiguration(
            $payload['days'] ?? [],
            $payload['progression_policy'] ?? 'off',
            $payload['progression_config'] ?? [],
        );

        return DB::transaction(function () use ($trainer, $payload) {
            $plans = collect();

            foreach ($payload['member_ids'] as $memberId) {
                $plan = WorkoutPlan::query()->create([
                    'gym_id' => $payload['gym_id'],
                    'branch_id' => $payload['branch_id'],
                    'member_id' => $memberId,
                    'trainer_id' => $trainer->id,
                    'independent_trainer_member_relationship_id' => $payload['independent_trainer_member_relationship_id'] ?? null,
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
                    'progression_policy' => $payload['progression_policy'] ?? 'off',
                    'progression_config' => $payload['progression_config'] ?? null,
                    'progression_version' => (int) ($payload['progression_version'] ?? 1),
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
        $this->assertExecutionModes($payload['days'] ?? []);
        $this->assertCoachingConfiguration(
            $payload['days'] ?? [],
            $payload['progression_policy'] ?? $plan->progression_policy ?? 'off',
            $payload['progression_config'] ?? $plan->progression_config ?? [],
        );

        return DB::transaction(function () use ($plan, $payload) {
            $plan->update([
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $plan->estimated_session_minutes,
                'equipment_profile' => $payload['equipment_profile'] ?? $plan->equipment_profile,
                'progression_policy' => $payload['progression_policy'] ?? $plan->progression_policy,
                'progression_config' => $payload['progression_config'] ?? $plan->progression_config,
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
        $this->assertExecutionModes($payload['days'] ?? []);
        $this->assertCoachingConfiguration(
            $payload['days'] ?? [],
            $payload['progression_policy'] ?? 'off',
            $payload['progression_config'] ?? [],
        );

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
                'progression_policy' => $payload['progression_policy'] ?? 'off',
                'progression_config' => $payload['progression_config'] ?? null,
                'progression_version' => (int) ($payload['progression_version'] ?? 1),
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
                        ...$this->executionModePayload($exercisePayload, $template),
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
        $this->assertExecutionModes($payload['days'] ?? []);
        $this->assertCoachingConfiguration(
            $payload['days'] ?? [],
            $payload['progression_policy'] ?? $template->progression_policy ?? 'off',
            $payload['progression_config'] ?? $template->progression_config ?? [],
        );

        return DB::transaction(function () use ($template, $payload) {
            $template->update([
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'program_type' => $payload['program_type'] ?? $template->program_type,
                'equipment_profile' => $payload['equipment_profile'] ?? $template->equipment_profile,
                'progression_policy' => $payload['progression_policy'] ?? $template->progression_policy,
                'progression_config' => $payload['progression_config'] ?? $template->progression_config,
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
                        ...$this->executionModePayload($exercisePayload, $template),
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
            'independent_trainer_member_relationship_id' => $payload['independent_trainer_member_relationship_id'] ?? null,
            'member_ids' => $payload['member_ids'],
            'workout_template_id' => $template->id,
            'name' => $payload['name'] ?? $template->name,
            'goal' => $payload['goal'] ?? $template->goal,
            'difficulty' => $payload['difficulty'] ?? $template->difficulty,
            'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? $template->estimated_session_minutes,
            'equipment_profile' => $payload['equipment_profile'] ?? $template->equipment_profile,
            'progression_policy' => $template->progression_policy,
            'progression_config' => $template->progression_config,
            'progression_version' => $template->progression_version,
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
                    ...$this->executionModePayload($exercise->toArray()),
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
     * @param  array<string, mixed>  $payload
     */
    public function createMemberPlan(User $member, array $payload): WorkoutPlan
    {
        $this->assertExecutionModes($payload['days'] ?? []);
        $this->assertCoachingConfiguration(
            $payload['days'] ?? [],
            $payload['progression_policy'] ?? 'off',
            $payload['progression_config'] ?? [],
        );

        return DB::transaction(function () use ($member, $payload) {
            $plan = WorkoutPlan::query()->create([
                // Member-created and public-catalog plans belong to the member,
                // not to whichever gym happens to be selected at creation time.
                'gym_id' => null,
                'branch_id' => null,
                'member_id' => $member->id,
                'trainer_id' => null,
                'created_by_user_id' => $member->id,
                'source_workout_book_id' => $payload['source_workout_book_id'] ?? null,
                'source_shared_workout_plan_id' => $payload['source_shared_workout_plan_id'] ?? null,
                'source_shared_by_user_id' => $payload['source_shared_by_user_id'] ?? null,
                'shared_adopted_at' => $payload['shared_adopted_at'] ?? null,
                'plan_origin' => $payload['plan_origin'] ?? 'member_custom',
                'is_member_editable' => true,
                'workout_template_id' => $payload['workout_template_id'] ?? null,
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'difficulty' => $payload['difficulty'] ?? null,
                'duration_weeks' => $payload['duration_weeks'],
                'estimated_session_minutes' => $payload['estimated_session_minutes'] ?? null,
                'equipment_profile' => $payload['equipment_profile'] ?? null,
                'progression_policy' => $payload['progression_policy'] ?? 'off',
                'progression_config' => $payload['progression_config'] ?? null,
                'progression_version' => (int) ($payload['progression_version'] ?? 1),
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
            'progression_policy' => $template->progression_policy,
            'progression_config' => $template->progression_config,
            'progression_version' => $template->progression_version,
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
                    ...$this->executionModePayload($exercise->toArray()),
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
            'progression_policy' => $plan->progression_policy,
            'progression_config' => $plan->progression_config,
            'progression_version' => $plan->progression_version,
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
                    ...$this->executionModePayload($exercise->toArray()),
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
                    ...$this->executionModePayload($exercisePayload, $plan),
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

    /** @param array<string, mixed> $payload */
    private function executionModePayload(array $payload, WorkoutPlan|WorkoutTemplate|null $defaults = null): array
    {
        return [
            'tracking_mode' => $payload['tracking_mode'] ?? 'reps',
            'planned_duration_seconds' => $payload['planned_duration_seconds'] ?? null,
            'planned_distance_meters' => $payload['planned_distance_meters'] ?? null,
            'planned_speed_kph' => $payload['planned_speed_kph'] ?? null,
            'planned_pace_seconds_per_km' => $payload['planned_pace_seconds_per_km'] ?? null,
            'target_resistance' => $payload['target_resistance'] ?? null,
            'target_machine_level' => $payload['target_machine_level'] ?? null,
            'is_per_side' => (bool) ($payload['is_per_side'] ?? false),
            'is_bodyweight' => (bool) ($payload['is_bodyweight'] ?? false),
            'group_key' => $payload['group_key'] ?? null,
            'group_type' => $payload['group_key'] ?? null ? ($payload['group_type'] ?? 'superset') : null,
            'group_order' => $payload['group_key'] ?? null ? ($payload['group_order'] ?? null) : null,
            'group_rounds' => $payload['group_key'] ?? null ? ($payload['group_rounds'] ?? 1) : null,
            'transition_seconds' => $payload['group_key'] ?? null ? ($payload['transition_seconds'] ?? 0) : null,
            'rest_after' => $payload['group_key'] ?? null ? ($payload['rest_after'] ?? 'group') : 'exercise',
            'progression_policy' => $payload['progression_policy'] ?? $defaults?->progression_policy ?? 'off',
            'progression_config' => $payload['progression_config'] ?? $defaults?->progression_config,
            'progression_version' => (int) ($payload['progression_version'] ?? $defaults?->progression_version ?? 1),
        ];
    }

    /** @param array<int, array<string, mixed>> $days */
    private function assertCoachingConfiguration(
        array $days,
        string $defaultProgressionPolicy = 'off',
        array $defaultProgressionConfig = [],
    ): void {
        $errors = [];
        foreach ($days as $dayIndex => $day) {
            $groups = collect($day['exercises'] ?? [])->filter(fn (array $exercise) => ! empty($exercise['group_key']))->groupBy('group_key');
            foreach ($groups as $groupKey => $exercises) {
                if ($exercises->count() < 2) {
                    $errors["days.{$dayIndex}.exercises"][] = "Group {$groupKey} must contain at least two exercises.";
                }
                if ($exercises->pluck('group_type')->filter()->unique()->count() > 1) {
                    $errors["days.{$dayIndex}.exercises"][] = "Group {$groupKey} must use one group type.";
                }
                if ($exercises->pluck('group_rounds')->filter()->unique()->count() > 1) {
                    $errors["days.{$dayIndex}.exercises"][] = "Group {$groupKey} must use one round count.";
                }
                $orders = $exercises->pluck('group_order')->filter()->map(fn ($order) => (int) $order);
                if ($orders->count() !== $orders->unique()->count()) {
                    $errors["days.{$dayIndex}.exercises"][] = "Group {$groupKey} must use unique group order values.";
                }
                $positions = collect($day['exercises'] ?? [])->keys()->filter(
                    fn ($position): bool => (($day['exercises'][$position]['group_key'] ?? null) === $groupKey),
                )->values();
                if ($positions->isNotEmpty() && ((int) $positions->last() - (int) $positions->first() + 1) !== $positions->count()) {
                    $errors["days.{$dayIndex}.exercises"][] = "Group {$groupKey} exercises must remain next to each other.";
                }
            }

            foreach ($day['exercises'] ?? [] as $exerciseIndex => $exercise) {
                $path = "days.{$dayIndex}.exercises.{$exerciseIndex}";
                if (! empty($exercise['group_type']) && empty($exercise['group_key'])) {
                    $errors["{$path}.group_key"][] = 'A grouped exercise requires a group key.';
                }
                if (! empty($exercise['group_key']) && empty($exercise['group_order'])) {
                    $errors["{$path}.group_order"][] = 'A grouped exercise requires its order within the group.';
                }
                $policy = $exercise['progression_policy'] ?? $defaultProgressionPolicy;
                if ($policy !== 'off' && ($exercise['tracking_mode'] ?? 'reps') !== 'reps') {
                    $errors["{$path}.progression_policy"][] = 'Initial progression policies support repetition exercises only.';
                }
                if ($policy === 'double_progression') {
                    $config = $exercise['progression_config'] ?? $defaultProgressionConfig;
                    $min = (int) ($config['min_reps'] ?? 0);
                    $max = (int) ($config['max_reps'] ?? 0);
                    if ($min < 1 || $max < $min) {
                        $errors["{$path}.progression_config"][] = 'Double progression requires a valid minimum and maximum repetition range.';
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<int, array<string, mixed>> $days */
    private function assertExecutionModes(array $days): void
    {
        $errors = [];
        foreach ($days as $dayIndex => $day) {
            foreach ($day['exercises'] ?? [] as $exerciseIndex => $exercise) {
                $mode = $exercise['tracking_mode'] ?? 'reps';
                $path = "days.{$dayIndex}.exercises.{$exerciseIndex}";
                if ($mode === 'timed' && (int) ($exercise['planned_duration_seconds'] ?? 0) < 1) {
                    $errors["{$path}.planned_duration_seconds"][] = 'A timed exercise requires a planned duration.';
                }
                if ($mode === 'distance' && (float) ($exercise['planned_distance_meters'] ?? 0) <= 0) {
                    $errors["{$path}.planned_distance_meters"][] = 'A distance exercise requires a planned distance.';
                }
                if (
                    $mode === 'cardio'
                    && (int) ($exercise['planned_duration_seconds'] ?? 0) < 1
                    && (float) ($exercise['planned_distance_meters'] ?? 0) <= 0
                ) {
                    $errors["{$path}.planned_duration_seconds"][] = 'A cardio exercise requires a planned duration or distance.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
