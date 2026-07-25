<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\StoreDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Http\Resources\Diet\DietPlanResource;
use App\Models\DietPlan;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Diet\DietPlanService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function __construct(private readonly DietPlanService $dietPlanService, private readonly TrainerScopeService $trainerScopeService, private readonly AuditLogService $auditLogService) {}

    public function index(Request $request)
    {
        $paginator = DietPlan::query()->with(['member', 'trainer', 'meals.items'])->where('trainer_id', $request->user()->id)->latest()->paginate($request->integer('per_page', 15));

        return $this->paginated($paginator, DietPlanResource::collection($paginator->getCollection()), 'Diet plans fetched successfully.');
    }

    public function store(StoreDietPlanRequest $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        foreach ($request->validated('member_ids') as $id) {
            $this->trainerScopeService->resolveAssignedMember($profile, User::query()->findOrFail($id));
        }
        $data = $request->validated();
        $data['gym_id'] = $profile->gym_id;
        $data['branch_id'] = $profile->branch_id;
        $plans = $this->dietPlanService->create($request->user(), $data);
        foreach ($plans as $plan) {
            $this->auditLogService->log(event: 'diet_plan.created', action: 'create', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, newValues: $plan->toArray());
        }

        return $this->success(DietPlanResource::collection($plans), 'Diet plan assigned successfully.', 201);
    }

    public function show(Request $request, DietPlan $dietPlan)
    {
        $this->assertAccess($request, $dietPlan);

        return $this->success(DietPlanResource::make($dietPlan->load(['member', 'trainer', 'meals.items'])));
    }

    public function update(UpdateDietPlanRequest $request, DietPlan $dietPlan)
    {
        $this->assertAccess($request, $dietPlan);

        $oldValues = $dietPlan->load('meals.items')->toArray();
        $plan = $this->dietPlanService->update($dietPlan, $request->user(), $request->validated());
        $this->auditLogService->log(event: 'diet_plan.updated', action: 'update', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, oldValues: $oldValues, newValues: $plan->toArray());

        return $this->success(DietPlanResource::make($plan), 'Diet plan updated successfully.');
    }

    public function destroy(Request $request, DietPlan $dietPlan)
    {
        $this->assertAccess($request, $dietPlan);
        $this->auditLogService->log(event: 'diet_plan.deleted', action: 'delete', request: $request, subject: $dietPlan, gym: $dietPlan->gym, branch: $dietPlan->branch, oldValues: $dietPlan->load('meals.items')->toArray());
        $dietPlan->delete();

        return $this->success(null, 'Diet plan deleted successfully.');
    }

    private function assertAccess(Request $request, DietPlan $plan): void
    {
        if ((int) $plan->trainer_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
        }
    }
}
