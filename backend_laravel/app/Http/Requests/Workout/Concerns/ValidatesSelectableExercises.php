<?php

namespace App\Http\Requests\Workout\Concerns;

use App\Models\Exercise;
use App\Models\WorkoutPlan;
use App\Models\WorkoutTemplate;
use App\Services\Authorization\ScopeResolver;
use App\Services\Member\MemberAppService;
use Illuminate\Validation\Validator;

trait ValidatesSelectableExercises
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $oldIds = [];
            foreach (['workoutPlan', 'workoutTemplate'] as $parameter) {
                $existing = $this->route($parameter);
                if ($existing instanceof WorkoutPlan || $existing instanceof WorkoutTemplate) {
                    $existing->loadMissing('days.exercises');
                    foreach ($existing->days as $day) {
                        foreach ($day->exercises as $exercise) {
                            $oldIds[] = (int) $exercise->exercise_id;
                        }
                    }
                }
            }

            $ids = collect($this->input('days', []))
                ->flatMap(fn ($day) => collect($day['exercises'] ?? [])
                    ->pluck('exercise_id'))
                ->map(fn ($id) => (int) $id)->unique()->all();
            $newIds = array_values(array_diff($ids, $oldIds));
            if ($newIds === []) {
                return;
            }

            $user = $this->user();
            $query = Exercise::query()->whereIn('id', $newIds)->where('is_active', true);
            if ($this->selectableExerciseAudience() === 'member') {
                $profile = app(MemberAppService::class)->memberProfileFor($user);
                $query->where('status', 'approved')
                    ->where(function ($scope) use ($profile): void {
                        $scope->where('is_global', true);
                        if ($profile?->gym_id) {
                            $scope->orWhere(function ($local) use ($profile): void {
                                $local->where('gym_id', $profile->gym_id);
                                if ($profile->branch_id) {
                                    $local->where(fn ($branch) => $branch
                                        ->whereNull('branch_id')
                                        ->orWhere('branch_id', $profile->branch_id));
                                }
                            });
                        }
                    });
            } else {
                $scope = app(ScopeResolver::class);
                $gymIds = $scope->gymsQuery($user)->pluck('gyms.id');
                $branchIds = $scope->branchesQuery($user)->pluck('branches.id');
                $query->where(fn ($status) => $status->where('status', 'approved')
                    ->orWhere('created_by_user_id', $user->id))
                    ->where(fn ($location) => $location->where('is_global', true)
                        ->orWhere(fn ($local) => $local->whereIn('gym_id', $gymIds)
                            ->where(fn ($branch) => $branch->whereNull('branch_id')
                                ->orWhereIn('branch_id', $branchIds))));
            }

            $allowedIds = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($this->input('days', []) as $dayIndex => $day) {
                foreach ($day['exercises'] ?? [] as $exerciseIndex => $exercise) {
                    $id = (int) ($exercise['exercise_id'] ?? 0);
                    if (! in_array($id, $oldIds, true) && ! in_array($id, $allowedIds, true)) {
                        $validator->errors()->add(
                            "days.$dayIndex.exercises.$exerciseIndex.exercise_id",
                            'Choose an exercise available in your exercise library.'
                        );
                    }
                }
            }
        });
    }

    abstract protected function selectableExerciseAudience(): string;
}
