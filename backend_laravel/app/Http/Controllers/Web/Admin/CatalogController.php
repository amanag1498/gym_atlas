<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ExerciseStatus;
use App\Http\Requests\PlatformAdmin\UpsertCityRequest;
use App\Http\Requests\PlatformAdmin\UpsertFitnessGoalRequest;
use App\Http\Requests\PlatformAdmin\UpsertFacilityRequest;
use App\Http\Requests\PlatformAdmin\StoreExerciseRequest;
use App\Http\Requests\PlatformAdmin\UpdateExerciseRequest;
use App\Http\Requests\PlatformAdmin\UpsertBannerRequest;
use App\Http\Requests\PlatformAdmin\UpsertTrainerSpecializationRequest;
use App\Models\City;
use App\Models\Exercise;
use App\Models\FitnessGoal;
use App\Models\Facility;
use App\Models\PlatformBanner;
use App\Models\TrainerProfile;
use App\Models\TrainerSpecialization;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformFacilityManagementService;
use App\Support\Workout\ExerciseBookCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformFacilityManagementService $platformFacilityManagementService,
    ) {
    }

    public function facilities(Request $request): View
    {
        $query = Facility::query()
            ->withCount(['gyms', 'branches'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('icon', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->string('status')->toString() === 'active';
            $query->where('is_active', $isActive);
        }

        $facilities = $query->paginate(15)->withQueryString();

        return view('web.admin.facilities.index', [
            'pageTitle' => 'Facilities',
            'breadcrumbs' => ['Platform', 'Facilities'],
            'facilities' => $facilities,
            'activeCount' => Facility::query()->where('is_active', true)->count(),
            'inactiveCount' => Facility::query()->where('is_active', false)->count(),
        ]);
    }

    public function exercises(Request $request): View
    {
        $query = Exercise::query()
            ->with(['creator:id,name'])
            ->withCount(['templateExercises', 'planExercises', 'sessionExercises', 'personalRecords'])
            ->where('is_global', true)
            ->where(function ($builder): void {
                $builder->where('status', 'approved')
                    ->orWhere('is_active', true);
            })
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('muscle_group', 'like', $search)
                    ->orWhere('equipment', 'like', $search);
            });
        }

        if ($request->filled('body_part')) {
            $requested = ExerciseBookCatalog::bodyPartForMuscleGroup(
                $request->string('body_part')->toString()
            );

            $exercises = $query->get()->filter(
                fn (Exercise $exercise) => ExerciseBookCatalog::bodyPartForMuscleGroup($exercise->muscle_group) === $requested
            )->values();
        } else {
            $exercises = $query->get();
        }

        return view('web.admin.exercises.index', [
            'pageTitle' => 'Exercise Book',
            'breadcrumbs' => ['Platform', 'Exercise Book'],
            'groupedExercises' => ExerciseBookCatalog::grouped($exercises),
            'totalExercises' => $exercises->count(),
            'activeExercises' => Exercise::query()->where('is_global', true)->where('is_active', true)->count(),
            'videoReadyExercises' => Exercise::query()->where('is_global', true)->whereNotNull('video_url')->count(),
            'imageReadyExercises' => Exercise::query()->where('is_global', true)->whereNotNull('image_url')->count(),
            'bodyPartOptions' => collect(ExerciseBookCatalog::BODY_PART_ORDER)
                ->mapWithKeys(fn (string $key) => [$key => ExerciseBookCatalog::bodyPartLabel($key)])
                ->all(),
        ]);
    }

    public function createExercise(): View
    {
        return view('web.admin.exercises.create', [
            'pageTitle' => 'Create Exercise',
            'breadcrumbs' => ['Platform', 'Exercise Book', 'Create'],
            'exercise' => new Exercise([
                'status' => ExerciseStatus::Approved->value,
                'is_active' => true,
                'is_global' => true,
            ]),
            'statusOptions' => $this->exerciseStatusOptions(),
        ]);
    }

    public function storeExercise(StoreExerciseRequest $request): RedirectResponse
    {
        $exercise = Exercise::query()->create([
            ...$request->validated(),
            'secondary_muscles' => $this->normalizeSecondaryMuscles($request->input('secondary_muscles')),
            'created_by_user_id' => $request->user()->id,
            'is_global' => true,
            'status' => $request->validated('status', ExerciseStatus::Approved->value),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditLogService->log(
            'web.platform.exercise.created',
            'create',
            $request,
            $exercise,
            null,
            null,
            null,
            $exercise->toArray(),
        );

        return redirect()
            ->route('web.admin.exercises.edit', $exercise)
            ->with('status', 'Exercise created successfully.');
    }

    public function editExercise(Exercise $exercise): View
    {
        abort_unless($exercise->is_global, 404);
        $exercise->load('creator')->loadCount(['templateExercises', 'planExercises', 'sessionExercises', 'personalRecords']);

        return view('web.admin.exercises.edit', [
            'pageTitle' => 'Edit Exercise',
            'breadcrumbs' => ['Platform', 'Exercise Book', $exercise->name],
            'exercise' => $exercise,
            'statusOptions' => $this->exerciseStatusOptions(),
        ]);
    }

    public function updateExercise(UpdateExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        abort_unless($exercise->is_global, 404);

        $oldValues = $exercise->toArray();
        $exercise->update([
            ...$request->validated(),
            'secondary_muscles' => $this->normalizeSecondaryMuscles($request->input('secondary_muscles')),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->auditLogService->log(
            'web.platform.exercise.updated',
            'update',
            $request,
            $exercise,
            null,
            null,
            $oldValues,
            $exercise->fresh()->toArray(),
        );

        return redirect()
            ->route('web.admin.exercises.edit', $exercise)
            ->with('status', 'Exercise updated successfully.');
    }

    public function banners(Request $request): View
    {
        $query = PlatformBanner::query()
            ->orderBy('sort_order')
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', $search)
                    ->orWhere('link_url', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $banners = $query->paginate(15)->withQueryString();

        return view('web.admin.banners.index', [
            'pageTitle' => 'Platform Banners',
            'breadcrumbs' => ['Platform', 'Banners'],
            'banners' => $banners,
            'activeCount' => PlatformBanner::query()->where('is_active', true)->count(),
            'inactiveCount' => PlatformBanner::query()->where('is_active', false)->count(),
            'bannerSlots' => PlatformBanner::query()->max('sort_order') ?: 0,
        ]);
    }

    public function createBanner(): View
    {
        return view('web.admin.banners.create', [
            'pageTitle' => 'Create Banner',
            'breadcrumbs' => ['Platform', 'Banners', 'Create'],
            'banner' => new PlatformBanner([
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function storeBanner(UpsertBannerRequest $request): RedirectResponse
    {
        $banner = PlatformBanner::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order'),
        ]);

        $this->auditLogService->log(
            'web.platform.banner.created',
            'create',
            $request,
            $banner,
            null,
            null,
            null,
            $banner->toArray(),
        );

        return redirect()
            ->route('web.admin.banners.edit', $banner)
            ->with('status', 'Banner created successfully.');
    }

    public function editBanner(PlatformBanner $banner): View
    {
        return view('web.admin.banners.edit', [
            'pageTitle' => 'Edit Banner',
            'breadcrumbs' => ['Platform', 'Banners', $banner->title],
            'banner' => $banner,
        ]);
    }

    public function updateBanner(UpsertBannerRequest $request, PlatformBanner $banner): RedirectResponse
    {
        $oldValues = $banner->toArray();
        $banner->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order'),
        ]);

        $this->auditLogService->log(
            'web.platform.banner.updated',
            'update',
            $request,
            $banner,
            null,
            null,
            $oldValues,
            $banner->fresh()->toArray(),
        );

        return redirect()
            ->route('web.admin.banners.edit', $banner)
            ->with('status', 'Banner updated successfully.');
    }

    public function destroyBanner(Request $request, PlatformBanner $banner): RedirectResponse
    {
        $oldValues = $banner->toArray();
        $banner->delete();

        $this->auditLogService->log(
            'web.platform.banner.deleted',
            'delete',
            $request,
            $banner,
            null,
            null,
            $oldValues,
        );

        return redirect()
            ->route('web.admin.banners.index')
            ->with('status', 'Banner deleted successfully.');
    }

    public function fitnessGoals(Request $request): View
    {
        $query = FitnessGoal::query()
            ->withCount('memberProfiles')
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->string('status')->toString() === 'active';
            $query->where('is_active', $isActive);
        }

        $fitnessGoals = $query->paginate(15)->withQueryString();

        return view('web.admin.fitness-goals.index', [
            'pageTitle' => 'Fitness Goals',
            'breadcrumbs' => ['Platform', 'Fitness Goals'],
            'fitnessGoals' => $fitnessGoals,
            'activeCount' => FitnessGoal::query()->where('is_active', true)->count(),
            'inactiveCount' => FitnessGoal::query()->where('is_active', false)->count(),
        ]);
    }

    public function createFitnessGoal(): View
    {
        return view('web.admin.fitness-goals.create', [
            'pageTitle' => 'Create Fitness Goal',
            'breadcrumbs' => ['Platform', 'Fitness Goals', 'Create'],
            'fitnessGoal' => new FitnessGoal([
                'sort_order' => 0,
                'status' => 'active',
                'is_active' => true,
            ]),
        ]);
    }

    public function storeFitnessGoal(UpsertFitnessGoalRequest $request): RedirectResponse
    {
        $goal = FitnessGoal::query()->create([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log(
            'web.platform.fitness_goal.created',
            'create',
            $request,
            $goal,
            null,
            null,
            null,
            $goal->toArray(),
        );

        return redirect()
            ->route('web.admin.fitness-goals.edit', $goal)
            ->with('status', 'Fitness goal created successfully.');
    }

    public function editFitnessGoal(FitnessGoal $fitnessGoal): View
    {
        $fitnessGoal->loadCount('memberProfiles');

        return view('web.admin.fitness-goals.edit', [
            'pageTitle' => 'Edit Fitness Goal',
            'breadcrumbs' => ['Platform', 'Fitness Goals', $fitnessGoal->name],
            'fitnessGoal' => $fitnessGoal,
        ]);
    }

    public function updateFitnessGoal(UpsertFitnessGoalRequest $request, FitnessGoal $fitnessGoal): RedirectResponse
    {
        $oldValues = $fitnessGoal->toArray();

        $fitnessGoal->update([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log(
            'web.platform.fitness_goal.updated',
            'update',
            $request,
            $fitnessGoal,
            null,
            null,
            $oldValues,
            $fitnessGoal->fresh()->toArray(),
        );

        return redirect()
            ->route('web.admin.fitness-goals.edit', $fitnessGoal)
            ->with('status', 'Fitness goal updated successfully.');
    }

    public function toggleFitnessGoalStatus(Request $request, FitnessGoal $fitnessGoal): RedirectResponse
    {
        $oldValues = $fitnessGoal->toArray();

        $fitnessGoal->update([
            'status' => $fitnessGoal->is_active ? 'inactive' : 'active',
            'is_active' => ! $fitnessGoal->is_active,
        ]);

        $this->auditLogService->log(
            'web.platform.fitness_goal.status.updated',
            'update',
            $request,
            $fitnessGoal,
            null,
            null,
            $oldValues,
            $fitnessGoal->fresh()->toArray(),
        );

        return back()->with(
            'status',
            'Fitness goal status updated to '.str($fitnessGoal->status)->title().'.',
        );
    }

    public function destroyFitnessGoal(Request $request, FitnessGoal $fitnessGoal): RedirectResponse
    {
        if (($fitnessGoal->member_profiles_count ?? null) === null) {
            $fitnessGoal->loadCount('memberProfiles');
        }

        if (($fitnessGoal->member_profiles_count ?? 0) > 0) {
            return back()->withErrors([
                'fitness_goal' => 'This fitness goal is already assigned to member profiles. Deactivate it instead of deleting.',
            ]);
        }

        $oldValues = $fitnessGoal->toArray();
        $goalName = $fitnessGoal->name;
        $fitnessGoal->delete();

        $this->auditLogService->log(
            'web.platform.fitness_goal.deleted',
            'delete',
            $request,
            $fitnessGoal,
            null,
            null,
            $oldValues,
            null,
        );

        return redirect()
            ->route('web.admin.fitness-goals.index')
            ->with('status', $goalName.' deleted successfully.');
    }

    public function trainerSpecializations(Request $request): View
    {
        $query = TrainerSpecialization::query()->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $trainerSpecializations = $query->paginate(15)->withQueryString();
        $trainerSpecializations->getCollection()->each(function (TrainerSpecialization $specialization): void {
            $specialization->trainer_profiles_count = $this->trainerProfilesUsing($specialization->name);
        });

        return view('web.admin.trainer-specializations.index', [
            'pageTitle' => 'Trainer Specializations',
            'breadcrumbs' => ['Platform', 'Trainer Specializations'],
            'trainerSpecializations' => $trainerSpecializations,
            'activeCount' => TrainerSpecialization::query()->where('is_active', true)->count(),
            'inactiveCount' => TrainerSpecialization::query()->where('is_active', false)->count(),
        ]);
    }

    public function createTrainerSpecialization(): View
    {
        return view('web.admin.trainer-specializations.create', [
            'pageTitle' => 'Create Trainer Specialization',
            'breadcrumbs' => ['Platform', 'Trainer Specializations', 'Create'],
            'trainerSpecialization' => new TrainerSpecialization([
                'sort_order' => 0,
                'status' => 'active',
                'is_active' => true,
            ]),
        ]);
    }

    public function storeTrainerSpecialization(UpsertTrainerSpecializationRequest $request): RedirectResponse
    {
        $specialization = TrainerSpecialization::query()->create([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log(
            'web.platform.trainer_specialization.created',
            'create',
            $request,
            $specialization,
            null,
            null,
            null,
            $specialization->toArray(),
        );

        return redirect()
            ->route('web.admin.trainer-specializations.edit', $specialization)
            ->with('status', 'Trainer specialization created successfully.');
    }

    public function editTrainerSpecialization(TrainerSpecialization $trainerSpecialization): View
    {
        $trainerSpecialization->trainer_profiles_count = $this->trainerProfilesUsing($trainerSpecialization->name);

        return view('web.admin.trainer-specializations.edit', [
            'pageTitle' => 'Edit Trainer Specialization',
            'breadcrumbs' => ['Platform', 'Trainer Specializations', $trainerSpecialization->name],
            'trainerSpecialization' => $trainerSpecialization,
        ]);
    }

    public function updateTrainerSpecialization(UpsertTrainerSpecializationRequest $request, TrainerSpecialization $trainerSpecialization): RedirectResponse
    {
        $oldValues = $trainerSpecialization->toArray();

        $trainerSpecialization->update([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log(
            'web.platform.trainer_specialization.updated',
            'update',
            $request,
            $trainerSpecialization,
            null,
            null,
            $oldValues,
            $trainerSpecialization->fresh()->toArray(),
        );

        return redirect()
            ->route('web.admin.trainer-specializations.edit', $trainerSpecialization)
            ->with('status', 'Trainer specialization updated successfully.');
    }

    public function toggleTrainerSpecializationStatus(Request $request, TrainerSpecialization $trainerSpecialization): RedirectResponse
    {
        $oldValues = $trainerSpecialization->toArray();

        $trainerSpecialization->update([
            'status' => $trainerSpecialization->is_active ? 'inactive' : 'active',
            'is_active' => ! $trainerSpecialization->is_active,
        ]);

        $this->auditLogService->log(
            'web.platform.trainer_specialization.status.updated',
            'update',
            $request,
            $trainerSpecialization,
            null,
            null,
            $oldValues,
            $trainerSpecialization->fresh()->toArray(),
        );

        return back()->with('status', 'Trainer specialization status updated to '.str($trainerSpecialization->status)->title().'.');
    }

    public function destroyTrainerSpecialization(Request $request, TrainerSpecialization $trainerSpecialization): RedirectResponse
    {
        if ($this->trainerProfilesUsing($trainerSpecialization->name) > 0) {
            return back()->withErrors([
                'trainer_specialization' => 'This specialization is already assigned to trainer profiles. Deactivate it instead of deleting.',
            ]);
        }

        $oldValues = $trainerSpecialization->toArray();
        $specializationName = $trainerSpecialization->name;
        $trainerSpecialization->delete();

        $this->auditLogService->log(
            'web.platform.trainer_specialization.deleted',
            'delete',
            $request,
            $trainerSpecialization,
            null,
            null,
            $oldValues,
            null,
        );

        return redirect()
            ->route('web.admin.trainer-specializations.index')
            ->with('status', $specializationName.' deleted successfully.');
    }

    public function createFacility(): View
    {
        return view('web.admin.facilities.create', [
            'pageTitle' => 'Create Facility',
            'breadcrumbs' => ['Platform', 'Facilities', 'Create'],
            'facility' => new Facility([
                'status' => 'active',
                'is_active' => true,
            ]),
        ]);
    }

    public function storeFacility(UpsertFacilityRequest $request): RedirectResponse
    {
        $facility = $this->platformFacilityManagementService->create($request->validated(), $request);

        return redirect()
            ->route('web.admin.facilities.edit', $facility)
            ->with('status', 'Facility created successfully.');
    }

    public function editFacility(Facility $facility): View
    {
        $facility->loadCount(['gyms', 'branches']);

        return view('web.admin.facilities.edit', [
            'pageTitle' => 'Edit Facility',
            'breadcrumbs' => ['Platform', 'Facilities', $facility->name],
            'facility' => $facility,
        ]);
    }

    public function updateFacility(UpsertFacilityRequest $request, Facility $facility): RedirectResponse
    {
        $this->platformFacilityManagementService->update($facility, $request->validated(), $request);

        return redirect()
            ->route('web.admin.facilities.edit', $facility)
            ->with('status', 'Facility updated successfully.');
    }

    public function toggleFacilityStatus(Request $request, Facility $facility): RedirectResponse
    {
        $facility = $this->platformFacilityManagementService->toggleStatus($facility, $request);

        return back()->with('status', 'Facility status updated to '.str($facility->status)->title().'.');
    }

    public function destroyFacility(Request $request, Facility $facility): RedirectResponse
    {
        if (! $this->platformFacilityManagementService->canDelete($facility)) {
            return back()->withErrors([
                'facility' => 'This facility is already used by gyms or branches. Deactivate it instead of deleting.',
            ]);
        }

        $facilityName = $facility->name;
        $this->platformFacilityManagementService->delete($facility, $request);

        return redirect()
            ->route('web.admin.facilities.index')
            ->with('status', $facilityName.' deleted successfully.');
    }

    public function cities(Request $request): View
    {
        $query = City::query()
            ->withCount(['gyms', 'branches'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('state', 'like', $search)
                    ->orWhere('country', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return view('web.admin.catalog.cities', [
            'pageTitle' => 'Cities',
            'breadcrumbs' => ['Platform', 'Cities'],
            'cities' => $query->paginate(20)->withQueryString(),
            'activeCount' => City::query()->where('is_active', true)->count(),
            'inactiveCount' => City::query()->where('is_active', false)->count(),
        ]);
    }

    public function storeCity(UpsertCityRequest $request): RedirectResponse
    {
        $city = City::query()->create($request->validated());
        $this->auditLogService->log('web.platform.city.created', 'create', $request, $city, null, null, null, $city->toArray());

        return back()->with('status', 'City created successfully.');
    }

    public function updateCity(UpsertCityRequest $request, City $city): RedirectResponse
    {
        $oldValues = $city->toArray();
        $city->update($request->validated());
        $this->auditLogService->log('web.platform.city.updated', 'update', $request, $city, null, null, $oldValues, $city->fresh()->toArray());

        return back()->with('status', 'City updated successfully.');
    }

    public function destroyCity(Request $request, City $city): RedirectResponse
    {
        $city->loadCount(['gyms', 'branches']);

        if (($city->gyms_count ?? 0) > 0 || ($city->branches_count ?? 0) > 0) {
            return back()->withErrors([
                'city' => 'This city is already used by gyms or branches. Deactivate it or update references before deleting.',
            ]);
        }

        $oldValues = $city->toArray();
        $city->delete();
        $this->auditLogService->log('web.platform.city.deleted', 'delete', $request, $city, null, null, $oldValues, null);

        return back()->with('status', 'City deleted successfully.');
    }

    private function trainerProfilesUsing(string $specialization): int
    {
        return TrainerProfile::query()
            ->where('specialization', $specialization)
            ->orWhereJsonContains('specializations', $specialization)
            ->count();
    }

    /**
     * @return array<string, string>
     */
    private function exerciseStatusOptions(): array
    {
        return collect(ExerciseStatus::cases())
            ->mapWithKeys(fn (ExerciseStatus $status) => [$status->value => str($status->value)->title()->toString()])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeSecondaryMuscles(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => trim((string) $item))->filter()->values()->all();
        }

        return str((string) $value)
            ->explode(',')
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
