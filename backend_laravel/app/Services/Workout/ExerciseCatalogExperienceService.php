<?php

namespace App\Services\Workout;

use App\Models\Exercise;
use App\Models\MemberEquipmentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExerciseCatalogExperienceService
{
    public const PRESETS = [
        'commercial_gym' => ['name' => 'Commercial gym', 'equipment' => []],
        'bodyweight_only' => ['name' => 'Bodyweight only', 'equipment' => ['body weight', 'bodyweight']],
        'dumbbells_and_bench' => ['name' => 'Dumbbells and bench', 'equipment' => ['body weight', 'bodyweight', 'dumbbell']],
        'resistance_bands' => ['name' => 'Resistance bands', 'equipment' => ['body weight', 'bodyweight', 'band', 'resistance band']],
        'home_gym' => ['name' => 'Home gym', 'equipment' => ['body weight', 'bodyweight', 'dumbbell', 'band', 'resistance band', 'kettlebell', 'stability ball', 'roller', 'rope']],
        'custom' => ['name' => 'Custom', 'equipment' => []],
    ];

    public function applyMemberExperience(Builder $query, Request $request, User $member): Builder
    {
        $query->withExists(['favoriteMembers as is_favourite' => fn (Builder $builder) => $builder->whereKey($member->id)]);

        if ($request->boolean('favourites')) {
            $query->whereHas('favoriteMembers', fn (Builder $builder) => $builder->whereKey($member->id));
        }

        if ($request->boolean('recent')) {
            $recent = DB::table('workout_session_exercises')
                ->join('workout_sessions', 'workout_sessions.id', '=', 'workout_session_exercises.workout_session_id')
                ->where('workout_sessions.member_id', $member->id)
                ->whereIn('workout_sessions.status', ['active', 'completed'])
                ->selectRaw('workout_session_exercises.exercise_id, MAX(workout_sessions.started_at) as recently_used_at')
                ->groupBy('workout_session_exercises.exercise_id');

            $query->joinSub($recent, 'member_recent_exercises', fn ($join) => $join
                ->on('member_recent_exercises.exercise_id', '=', 'exercises.id'))
                ->addSelect('exercises.*', 'member_recent_exercises.recently_used_at')
                ->reorder('member_recent_exercises.recently_used_at', 'desc')
                ->orderByDesc('exercises.id');
        }

        $equipment = $this->equipmentForRequest($request, $member);
        if ($equipment !== null && $equipment !== []) {
            $query->whereIn(DB::raw('LOWER(exercises.equipment)'), array_map('strtolower', $equipment));
        }

        return $query;
    }

    public function applyTrainerRecent(Builder $query, Request $request, User $trainer): Builder
    {
        if (! $request->boolean('recent')) {
            return $query;
        }

        $recent = DB::table('workout_plan_exercises')
            ->join('workout_plan_days', 'workout_plan_days.id', '=', 'workout_plan_exercises.workout_plan_day_id')
            ->join('workout_plans', 'workout_plans.id', '=', 'workout_plan_days.workout_plan_id')
            ->where('workout_plans.trainer_id', $trainer->id)
            ->selectRaw('workout_plan_exercises.exercise_id, MAX(COALESCE(workout_plans.assigned_at, workout_plan_exercises.created_at)) as recently_used_at')
            ->groupBy('workout_plan_exercises.exercise_id');

        return $query->joinSub($recent, 'trainer_recent_exercises', fn ($join) => $join
            ->on('trainer_recent_exercises.exercise_id', '=', 'exercises.id'))
            ->addSelect('exercises.*', 'trainer_recent_exercises.recently_used_at')
            ->reorder('trainer_recent_exercises.recently_used_at', 'desc')
            ->orderByDesc('exercises.id');
    }

    public function curatedSubstitutions(
        Exercise $exercise,
        ?array $availableEquipment = null,
        ?callable $visibilityScope = null,
    ): Collection {
        return $exercise->substitutions()
            ->with(['substitute.translations', 'substitute.previewMedia'])
            ->where('is_active', true)
            ->whereHas('substitute', function (Builder $query) use ($availableEquipment, $visibilityScope): void {
                $query->where('is_active', true)->where('status', 'approved');
                if ($visibilityScope) {
                    $visibilityScope($query);
                }
                if ($availableEquipment !== null && $availableEquipment !== []) {
                    $query->whereIn(DB::raw('LOWER(equipment)'), array_map('strtolower', $availableEquipment));
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function equipmentForRequest(Request $request, User $member): ?array
    {
        if ($request->filled('equipment_profile_id')) {
            $profile = MemberEquipmentProfile::query()
                ->where('user_id', $member->id)
                ->find($request->integer('equipment_profile_id'));
            if (! $profile) {
                throw ValidationException::withMessages(['equipment_profile_id' => ['The selected equipment profile is not available.']]);
            }

            return $this->equipmentForProfile($profile);
        }

        if ($request->has('available_equipment')) {
            return collect((array) $request->input('available_equipment'))
                ->flatMap(fn ($value) => explode(',', (string) $value))
                ->map(fn ($value) => trim((string) $value))
                ->filter()->unique(fn ($value) => strtolower($value))->values()->all();
        }

        return null;
    }

    public function equipmentForProfile(MemberEquipmentProfile $profile): array
    {
        if ($profile->preset_key !== 'custom') {
            return self::PRESETS[$profile->preset_key]['equipment'] ?? [];
        }

        return collect($profile->equipment ?? [])->map(fn ($value) => trim((string) $value))
            ->filter()->unique(fn ($value) => strtolower($value))->values()->all();
    }
}
