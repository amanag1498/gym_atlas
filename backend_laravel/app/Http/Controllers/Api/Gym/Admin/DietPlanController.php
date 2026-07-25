<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\StoreDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Http\Resources\Diet\DietPlanResource;
use App\Models\DietPlan;
use App\Models\MemberProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Diet\DietPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function __construct(private readonly ScopeResolver $scopeResolver, private readonly DietPlanService $dietPlanService, private readonly AuditLogService $auditLogService) {}

    public function index(Request $request)
    {
        $gymId = $request->integer('gym_id') ?: $request->header('X-Gym-Id');
        $this->assertScope($request, (int) $gymId, $request->integer('branch_id') ?: $request->header('X-Branch-Id'));
        $branchId = $request->integer('branch_id') ?: $request->header('X-Branch-Id');
        $paginator = DietPlan::query()
            ->with(['member', 'trainer', 'meals.items'])
            ->where('gym_id', $gymId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('member_id'), fn ($q) => $q->where('member_id', $request->integer('member_id')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($paginator, DietPlanResource::collection($paginator->getCollection()), 'Diet plans fetched successfully.');
    }

    public function store(StoreDietPlanRequest $request)
    {
        $data = $request->validated();
        $this->assertScope($request, $data['gym_id'], $data['branch_id'] ?? null);
        foreach ($data['member_ids'] as $memberId) {
            $this->assertMember($memberId, $data['gym_id'], $data['branch_id'] ?? null);
        }

        $plans = $this->dietPlanService->create($request->user(), $data);
        foreach ($plans as $plan) {
            $this->auditLogService->log(event: 'diet_plan.created', action: 'create', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, newValues: $plan->toArray());
        }

        return $this->success(DietPlanResource::collection($plans), 'Diet plan assigned successfully.', 201);
    }

    public function show(Request $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);

        return $this->success(DietPlanResource::make($dietPlan->load(['member', 'trainer', 'meals.items'])));
    }

    public function update(UpdateDietPlanRequest $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);

        $oldValues = $dietPlan->load('meals.items')->toArray();
        $plan = $this->dietPlanService->update($dietPlan, $request->user(), $request->validated());
        $this->auditLogService->log(event: 'diet_plan.updated', action: 'update', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, oldValues: $oldValues, newValues: $plan->toArray());

        return $this->success(DietPlanResource::make($plan), 'Diet plan updated successfully.');
    }

    public function destroy(Request $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);
        $this->auditLogService->log(event: 'diet_plan.deleted', action: 'delete', request: $request, subject: $dietPlan, gym: $dietPlan->gym, branch: $dietPlan->branch, oldValues: $dietPlan->load('meals.items')->toArray());
        $dietPlan->delete();

        return $this->success(null, 'Diet plan deleted successfully.');
    }

    private function assertScope(Request $request, int $gymId, mixed $branchId): void
    {
        if (! $this->scopeResolver->canAccessGym($request->user(), $gymId) || ($branchId && ! $this->scopeResolver->canAccessBranch($request->user(), $branchId))) {
            throw ValidationException::withMessages(['gym_id' => ['You do not have access to this diet plan scope.']]);
        }
    }

    private function assertMember(int $memberId, int $gymId, mixed $branchId): void
    {
        if (! MemberProfile::query()->where('user_id', $memberId)->where('gym_id', $gymId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->exists()) {
            throw ValidationException::withMessages(['member_ids' => ['Each member must belong to the selected gym and branch.']]);
        }
    }
}
