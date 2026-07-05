<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\UpsertFitnessGoalRequest;
use App\Http\Requests\PlatformAdmin\UpsertBannerRequest;
use App\Http\Requests\PlatformAdmin\UpsertCityRequest;
use App\Http\Requests\PlatformAdmin\UpsertFacilityRequest;
use App\Http\Requests\PlatformAdmin\UpsertTrainerSpecializationRequest;
use App\Http\Resources\Catalog\FitnessGoalResource;
use App\Http\Resources\Catalog\TrainerSpecializationResource;
use App\Http\Resources\Gym\CityResource;
use App\Http\Resources\Gym\FacilityResource;
use App\Http\Resources\Platform\PlatformBannerResource;
use App\Models\City;
use App\Models\FitnessGoal;
use App\Models\Facility;
use App\Models\PlatformBanner;
use App\Models\TrainerProfile;
use App\Models\TrainerSpecialization;
use App\Support\Api\ApiResponse;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformFacilityManagementService;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformFacilityManagementService $platformFacilityManagementService,
    ) {
    }

    public function cities(Request $request)
    {
        $paginator = City::query()->latest('id')->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, CityResource::collection($paginator->getCollection()));
    }

    public function storeCity(UpsertCityRequest $request)
    {
        $city = City::query()->create($request->validated());

        $this->auditLogService->log('platform.city.created', 'create', $request, $city, null, null, null, $city->toArray());

        return $this->success(CityResource::make($city), 'City created successfully.', 201);
    }

    public function updateCity(UpsertCityRequest $request, City $city)
    {
        $oldValues = $city->toArray();
        $city->update($request->validated());

        $this->auditLogService->log('platform.city.updated', 'update', $request, $city, null, null, $oldValues, $city->fresh()->toArray());

        return $this->success(CityResource::make($city->fresh()));
    }

    public function deleteCity(Request $request, City $city)
    {
        $oldValues = $city->toArray();
        $city->delete();

        $this->auditLogService->log('platform.city.deleted', 'delete', $request, $city, null, null, $oldValues, null);

        return $this->success(null, 'City deleted successfully.');
    }

    public function facilities(Request $request)
    {
        $query = Facility::query()
            ->withCount(['gyms', 'branches'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where('name', 'like', $search);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, FacilityResource::collection($paginator->getCollection()));
    }

    public function fitnessGoals(Request $request)
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
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $paginator = $query->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, FitnessGoalResource::collection($paginator->getCollection()));
    }

    public function showFitnessGoal(FitnessGoal $fitnessGoal)
    {
        $fitnessGoal->loadCount('memberProfiles');

        return $this->success(FitnessGoalResource::make($fitnessGoal), 'Fitness goal loaded successfully.');
    }

    public function storeFitnessGoal(UpsertFitnessGoalRequest $request)
    {
        $goal = FitnessGoal::query()->create([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log('platform.fitness_goal.created', 'create', $request, $goal, null, null, null, $goal->toArray());

        return $this->success(FitnessGoalResource::make($goal), 'Fitness goal created successfully.', 201);
    }

    public function updateFitnessGoal(UpsertFitnessGoalRequest $request, FitnessGoal $fitnessGoal)
    {
        $oldValues = $fitnessGoal->toArray();
        $fitnessGoal->update([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log('platform.fitness_goal.updated', 'update', $request, $fitnessGoal, null, null, $oldValues, $fitnessGoal->fresh()->toArray());

        return $this->success(FitnessGoalResource::make($fitnessGoal->fresh()), 'Fitness goal updated successfully.');
    }

    public function toggleFitnessGoalStatus(Request $request, FitnessGoal $fitnessGoal)
    {
        $oldValues = $fitnessGoal->toArray();
        $fitnessGoal->update([
            'status' => $fitnessGoal->is_active ? 'inactive' : 'active',
            'is_active' => ! $fitnessGoal->is_active,
        ]);

        $this->auditLogService->log('platform.fitness_goal.status.updated', 'update', $request, $fitnessGoal, null, null, $oldValues, $fitnessGoal->fresh()->toArray());

        return $this->success(FitnessGoalResource::make($fitnessGoal), 'Fitness goal status updated successfully.');
    }

    public function deleteFitnessGoal(Request $request, FitnessGoal $fitnessGoal)
    {
        $fitnessGoal->loadCount('memberProfiles');

        if (($fitnessGoal->member_profiles_count ?? 0) > 0) {
            return ApiResponse::error('Fitness goal is already assigned to member profiles. Deactivate it instead.', 422);
        }

        $oldValues = $fitnessGoal->toArray();
        $fitnessGoal->delete();

        $this->auditLogService->log('platform.fitness_goal.deleted', 'delete', $request, $fitnessGoal, null, null, $oldValues, null);

        return $this->success(null, 'Fitness goal deleted successfully.');
    }

    public function trainerSpecializations(Request $request)
    {
        $query = TrainerSpecialization::query()
            ->latest('id');

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

        $paginator = $query->paginate((int) $request->integer('per_page', 20));

        $paginator->getCollection()->each(function (TrainerSpecialization $specialization): void {
            $specialization->trainer_profiles_count = $this->trainerProfilesUsing($specialization->name);
        });

        return $this->paginated($paginator, TrainerSpecializationResource::collection($paginator->getCollection()));
    }

    public function showTrainerSpecialization(TrainerSpecialization $trainerSpecialization)
    {
        $trainerSpecialization->trainer_profiles_count = $this->trainerProfilesUsing($trainerSpecialization->name);

        return $this->success(TrainerSpecializationResource::make($trainerSpecialization), 'Trainer specialization loaded successfully.');
    }

    public function storeTrainerSpecialization(UpsertTrainerSpecializationRequest $request)
    {
        $specialization = TrainerSpecialization::query()->create([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log('platform.trainer_specialization.created', 'create', $request, $specialization, null, null, null, $specialization->toArray());

        return $this->success(TrainerSpecializationResource::make($specialization), 'Trainer specialization created successfully.', 201);
    }

    public function updateTrainerSpecialization(UpsertTrainerSpecializationRequest $request, TrainerSpecialization $trainerSpecialization)
    {
        $oldValues = $trainerSpecialization->toArray();
        $trainerSpecialization->update([
            ...$request->safe()->except('status'),
            'status' => $request->validated('status'),
            'is_active' => $request->validated('status') === 'active',
            'slug' => str($request->validated('name'))->slug()->toString(),
        ]);

        $this->auditLogService->log('platform.trainer_specialization.updated', 'update', $request, $trainerSpecialization, null, null, $oldValues, $trainerSpecialization->fresh()->toArray());

        return $this->success(TrainerSpecializationResource::make($trainerSpecialization->fresh()), 'Trainer specialization updated successfully.');
    }

    public function toggleTrainerSpecializationStatus(Request $request, TrainerSpecialization $trainerSpecialization)
    {
        $oldValues = $trainerSpecialization->toArray();
        $trainerSpecialization->update([
            'status' => $trainerSpecialization->is_active ? 'inactive' : 'active',
            'is_active' => ! $trainerSpecialization->is_active,
        ]);

        $this->auditLogService->log('platform.trainer_specialization.status.updated', 'update', $request, $trainerSpecialization, null, null, $oldValues, $trainerSpecialization->fresh()->toArray());

        return $this->success(TrainerSpecializationResource::make($trainerSpecialization), 'Trainer specialization status updated successfully.');
    }

    public function deleteTrainerSpecialization(Request $request, TrainerSpecialization $trainerSpecialization)
    {
        if ($this->trainerProfilesUsing($trainerSpecialization->name) > 0) {
            return ApiResponse::error('Trainer specialization is already assigned to trainer profiles. Deactivate it instead.', 422);
        }

        $oldValues = $trainerSpecialization->toArray();
        $trainerSpecialization->delete();

        $this->auditLogService->log('platform.trainer_specialization.deleted', 'delete', $request, $trainerSpecialization, null, null, $oldValues, null);

        return $this->success(null, 'Trainer specialization deleted successfully.');
    }

    public function showFacility(Facility $facility)
    {
        $facility->loadCount(['gyms', 'branches']);

        return $this->success(FacilityResource::make($facility), 'Facility loaded successfully.');
    }

    public function storeFacility(UpsertFacilityRequest $request)
    {
        $facility = $this->platformFacilityManagementService->create($request->validated(), $request);

        return $this->success(FacilityResource::make($facility), 'Facility created successfully.', 201);
    }

    public function updateFacility(UpsertFacilityRequest $request, Facility $facility)
    {
        $facility = $this->platformFacilityManagementService->update($facility, $request->validated(), $request);

        return $this->success(FacilityResource::make($facility), 'Facility updated successfully.');
    }

    public function toggleFacilityStatus(Request $request, Facility $facility)
    {
        $facility = $this->platformFacilityManagementService->toggleStatus($facility, $request);

        return $this->success(FacilityResource::make($facility), 'Facility status updated successfully.');
    }

    public function deleteFacility(Request $request, Facility $facility)
    {
        if (! $this->platformFacilityManagementService->canDelete($facility)) {
            return ApiResponse::error('Facility is already used by gyms or branches. Deactivate it instead.', 422);
        }

        $this->platformFacilityManagementService->delete($facility, $request);

        return $this->success(null, 'Facility deleted successfully.');
    }

    public function banners(Request $request)
    {
        $paginator = PlatformBanner::query()->orderBy('sort_order')->latest('id')->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, PlatformBannerResource::collection($paginator->getCollection()));
    }

    public function storeBanner(UpsertBannerRequest $request)
    {
        $banner = PlatformBanner::query()->create($request->validated());

        $this->auditLogService->log('platform.banner.created', 'create', $request, $banner, null, null, null, $banner->toArray());

        return $this->success(PlatformBannerResource::make($banner), 'Banner created successfully.', 201);
    }

    public function updateBanner(UpsertBannerRequest $request, PlatformBanner $banner)
    {
        $oldValues = $banner->toArray();
        $banner->update($request->validated());

        $this->auditLogService->log('platform.banner.updated', 'update', $request, $banner, null, null, $oldValues, $banner->fresh()->toArray());

        return $this->success(PlatformBannerResource::make($banner->fresh()));
    }

    public function deleteBanner(Request $request, PlatformBanner $banner)
    {
        $oldValues = $banner->toArray();
        $banner->delete();

        $this->auditLogService->log('platform.banner.deleted', 'delete', $request, $banner, null, null, $oldValues, null);

        return $this->success(null, 'Banner deleted successfully.');
    }

    private function trainerProfilesUsing(string $specialization): int
    {
        return TrainerProfile::query()
            ->where('specialization', $specialization)
            ->orWhereJsonContains('specializations', $specialization)
            ->count();
    }
}
