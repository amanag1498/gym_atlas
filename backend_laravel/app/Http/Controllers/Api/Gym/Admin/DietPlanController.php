<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\StoreDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Http\Resources\Diet\DietPlanResource;
use App\Http\Resources\Diet\DietPlanTemplateResource;
use App\Models\Branch;
use App\Models\DietPlan;
use App\Models\DietPlanTemplate;
use App\Models\MemberProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Diet\DietPlanService;
use App\Services\Diet\DietPlanTemplateService;
use App\Services\Members\GymMemberAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scopeResolver,
        private readonly DietPlanService $dietPlanService,
        private readonly DietPlanTemplateService $dietPlanTemplateService,
        private readonly AuditLogService $auditLogService,
        private readonly GymMemberAccessService $gymMemberAccessService,
    ) {}

    public function index(Request $request)
    {
        $gymId = $request->integer('gym_id') ?: $request->header('X-Gym-Id');
        $this->assertScope($request, (int) $gymId, $request->integer('branch_id') ?: $request->header('X-Branch-Id'));
        $branchId = $request->integer('branch_id') ?: $request->header('X-Branch-Id');
        $paginator = DietPlan::query()
            ->with(['member', 'trainer', 'meals.items'])
            ->where('gym_id', $gymId)
            ->whereHas('member.memberProfiles', function ($profile) use ($gymId): void {
                $profile->where('gym_id', $gymId);
                $this->gymMemberAccessService->scopeAccessibleProfiles($profile);
            })
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

    public function templates(Request $request)
    {
        $gymId = $request->integer('gym_id') ?: $request->header('X-Gym-Id');
        $branchId = $request->integer('branch_id') ?: $request->header('X-Branch-Id');
        $this->assertScope($request, (int) $gymId, $branchId);

        $paginator = DietPlanTemplate::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            $paginator,
            DietPlanTemplateResource::collection($paginator->getCollection()),
            'Global diet templates fetched successfully.',
        );
    }

    public function assignTemplate(Request $request, DietPlanTemplate $dietPlanTemplate)
    {
        $data = $request->validate([
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $this->assertScope($request, $data['gym_id'], $data['branch_id'] ?? null);
        foreach ($data['member_ids'] as $memberId) {
            $this->assertMember(
                $memberId,
                $data['gym_id'],
                $data['branch_id'] ?? null,
            );
        }

        $payload = array_merge(
            $this->dietPlanTemplateService->planPayload($dietPlanTemplate),
            array_filter([
                'name' => $data['name'] ?? null,
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
            ], static fn ($value) => $value !== null && $value !== ''),
            [
                'gym_id' => $data['gym_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'member_ids' => $data['member_ids'],
                'status' => 'active',
            ],
        );

        $plans = $this->dietPlanService->create($request->user(), $payload);
        foreach ($plans as $plan) {
            $this->auditLogService->log(
                event: 'diet_plan.assigned_from_global_template',
                action: 'create',
                request: $request,
                subject: $plan,
                gym: $plan->gym,
                branch: $plan->branch,
                newValues: $plan->toArray(),
                context: ['diet_template_id' => $dietPlanTemplate->id],
            );
        }

        return $this->success(
            DietPlanResource::collection($plans),
            'Global diet template assigned successfully.',
            201,
        );
    }

    public function show(Request $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);
        $this->assertMember($dietPlan->member_id, $dietPlan->gym_id, $dietPlan->branch_id);

        return $this->success(DietPlanResource::make($dietPlan->load(['member', 'trainer', 'meals.items'])));
    }

    public function update(UpdateDietPlanRequest $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);
        $this->assertMember($dietPlan->member_id, $dietPlan->gym_id, $dietPlan->branch_id);

        $oldValues = $dietPlan->load('meals.items')->toArray();
        $plan = $this->dietPlanService->update($dietPlan, $request->user(), $request->validated());
        $this->auditLogService->log(event: 'diet_plan.updated', action: 'update', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, oldValues: $oldValues, newValues: $plan->toArray());

        return $this->success(DietPlanResource::make($plan), 'Diet plan updated successfully.');
    }

    public function destroy(Request $request, DietPlan $dietPlan)
    {
        $this->assertScope($request, $dietPlan->gym_id, $dietPlan->branch_id);
        $this->assertMember($dietPlan->member_id, $dietPlan->gym_id, $dietPlan->branch_id);
        $this->auditLogService->log(event: 'diet_plan.deleted', action: 'delete', request: $request, subject: $dietPlan, gym: $dietPlan->gym, branch: $dietPlan->branch, oldValues: $dietPlan->load('meals.items')->toArray());
        $dietPlan->delete();

        return $this->success(null, 'Diet plan deleted successfully.');
    }

    private function assertScope(Request $request, int $gymId, mixed $branchId): void
    {
        $branchIsInGym = ! $branchId || Branch::query()
            ->whereKey($branchId)
            ->where('gym_id', $gymId)
            ->exists();

        if (! $branchIsInGym || ! $this->scopeResolver->canAccessGym($request->user(), $gymId) || ($branchId && ! $this->scopeResolver->canAccessBranch($request->user(), $branchId))) {
            throw ValidationException::withMessages(['gym_id' => ['You do not have access to this diet plan scope.']]);
        }
    }

    private function assertMember(int $memberId, int $gymId, mixed $branchId): void
    {
        $query = MemberProfile::query()->where('user_id', $memberId)->where('gym_id', $gymId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $this->gymMemberAccessService->scopeAccessibleProfiles($query);
        if (! $query->exists()) {
            throw ValidationException::withMessages(['member_ids' => ['Each member must belong to the selected gym and branch.']]);
        }
    }
}
