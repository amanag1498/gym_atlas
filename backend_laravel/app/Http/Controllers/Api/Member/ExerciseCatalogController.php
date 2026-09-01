<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Workout\ExerciseResource;
use App\Http\Resources\Workout\PersonalRecordResource;
use App\Http\Resources\Workout\WorkoutSessionResource;
use App\Models\Exercise;
use App\Models\MemberEquipmentProfile;
use App\Models\MemberFavoriteExercise;
use App\Models\PersonalRecord;
use App\Models\WorkoutSession;
use App\Services\Member\MemberAppService;
use App\Services\Workout\ExerciseCatalogExperienceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExerciseCatalogController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly ExerciseCatalogExperienceService $experienceService,
    ) {}

    public function show(Request $request, Exercise $exercise)
    {
        $exercise = $this->visibleQuery($request)
            ->withExists(['favoriteMembers as is_favourite' => fn (Builder $query) => $query->whereKey($request->user()->id)])
            ->with(['translations', 'previewMedia'])
            ->findOrFail($exercise->id);

        $sessions = WorkoutSession::query()
            ->with(['exercises' => fn ($query) => $query->where('exercise_id', $exercise->id)->with('sets')])
            ->where('member_id', $request->user()->id)
            ->whereHas('exercises', fn ($query) => $query->where('exercise_id', $exercise->id))
            ->latest('started_at')->limit(5)->get();
        $record = PersonalRecord::query()->where('member_id', $request->user()->id)
            ->where('exercise_id', $exercise->id)->first();
        $equipment = $this->experienceService->equipmentForRequest($request, $request->user());
        $substitutions = $this->experienceService->curatedSubstitutions(
            $exercise,
            $equipment,
            fn (Builder $query) => $this->applyMemberVisibility($query, $request),
        );

        return $this->success([
            'exercise' => ExerciseResource::make($exercise),
            'recent_history' => WorkoutSessionResource::collection($sessions),
            'personal_record' => $record ? PersonalRecordResource::make($record) : null,
            'substitutions' => $substitutions->map(fn ($mapping) => [
                'exercise' => ExerciseResource::make($mapping->substitute),
                'reason' => $mapping->reason,
                'priority' => $mapping->priority,
                'requires_trainer_approval' => $mapping->requires_trainer_approval,
                'selection_basis' => 'curated',
            ])->values(),
            'substitution_notice' => 'Substitutions are curated catalog options, not medical advice. Trainer approval may be required.',
        ], 'Exercise details fetched successfully.');
    }

    public function favourite(Request $request, Exercise $exercise)
    {
        $this->visibleQuery($request)->findOrFail($exercise->id);
        MemberFavoriteExercise::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'exercise_id' => $exercise->id,
        ]);

        return $this->success(['exercise_id' => $exercise->id, 'is_favourite' => true], 'Exercise added to favourites.');
    }

    public function unfavourite(Request $request, Exercise $exercise)
    {
        MemberFavoriteExercise::query()->where('user_id', $request->user()->id)
            ->where('exercise_id', $exercise->id)->delete();

        return $this->success(['exercise_id' => $exercise->id, 'is_favourite' => false], 'Exercise removed from favourites.');
    }

    public function equipmentProfiles(Request $request)
    {
        return $this->success([
            'presets' => collect(ExerciseCatalogExperienceService::PRESETS)->map(fn ($preset, $key) => [
                'key' => $key, 'name' => $preset['name'], 'equipment' => $preset['equipment'],
            ])->values(),
            'profiles' => MemberEquipmentProfile::query()->where('user_id', $request->user()->id)
                ->orderByDesc('is_default')->orderBy('name')->get(),
        ], 'Equipment profiles fetched successfully.');
    }

    public function storeEquipmentProfile(Request $request)
    {
        $data = $request->validate($this->equipmentProfileRules());
        $profile = DB::transaction(function () use ($request, $data): MemberEquipmentProfile {
            if ($data['is_default'] ?? false) {
                MemberEquipmentProfile::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
            }

            return MemberEquipmentProfile::query()->create($data + ['user_id' => $request->user()->id]);
        });

        return $this->success($profile, 'Equipment profile created successfully.', 201);
    }

    public function updateEquipmentProfile(Request $request, MemberEquipmentProfile $equipmentProfile)
    {
        abort_unless((int) $equipmentProfile->user_id === (int) $request->user()->id, 404);
        $data = $request->validate($this->equipmentProfileRules());
        DB::transaction(function () use ($request, $equipmentProfile, $data): void {
            if ($data['is_default'] ?? false) {
                MemberEquipmentProfile::query()->where('user_id', $request->user()->id)
                    ->whereKeyNot($equipmentProfile->id)->update(['is_default' => false]);
            }
            $equipmentProfile->update($data);
        });

        return $this->success($equipmentProfile->fresh(), 'Equipment profile updated successfully.');
    }

    public function destroyEquipmentProfile(Request $request, MemberEquipmentProfile $equipmentProfile)
    {
        abort_unless((int) $equipmentProfile->user_id === (int) $request->user()->id, 404);
        $equipmentProfile->delete();

        return $this->success(null, 'Equipment profile deleted successfully.');
    }

    private function visibleQuery(Request $request): Builder
    {
        return $this->applyMemberVisibility(Exercise::query(), $request)
            ->where('is_active', true)->where('status', 'approved');
    }

    private function applyMemberVisibility(Builder $query, Request $request): Builder
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());

        return $query
            ->where(function (Builder $builder) use ($profile): void {
                $builder->where('is_global', true)->orWhere(function (Builder $scoped) use ($profile): void {
                    if (! $profile?->gym_id) {
                        $scoped->whereRaw('1 = 0');

                        return;
                    }
                    $scoped->where('gym_id', $profile->gym_id)
                        ->when($profile->branch_id, fn (Builder $query, $branchId) => $query
                            ->where(fn (Builder $branch) => $branch->whereNull('branch_id')->orWhere('branch_id', $branchId)));
                });
            });
    }

    private function equipmentProfileRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'preset_key' => ['required', Rule::in(array_keys(ExerciseCatalogExperienceService::PRESETS))],
            'equipment' => ['nullable', 'required_if:preset_key,custom', 'array', 'min:1', 'max:50'],
            'equipment.*' => ['string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
