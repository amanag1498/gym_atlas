<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\StoreDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Http\Resources\Diet\DietPlanResource;
use App\Http\Resources\Diet\DietPlanTemplateResource;
use App\Models\DietPlan;
use App\Models\DietPlanTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Diet\DietPlanService;
use App\Services\Diet\DietPlanTemplateService;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function __construct(
        private readonly DietPlanService $dietPlanService,
        private readonly DietPlanTemplateService $dietPlanTemplateService,
        private readonly TrainerScopeService $trainerScopeService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = DietPlan::query()->with(['member', 'trainer', 'meals.items'])->where('trainer_id', $request->user()->id)->where('gym_id', $profile->gym_id)->when($profile->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))->latest()->paginate($request->integer('per_page', 15));

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

    public function templates(Request $request)
    {
        $this->trainerScopeService->resolveTrainerProfile($request);
        $templates = DietPlanTemplate::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $this->success(
            DietPlanTemplateResource::collection($templates),
            'Global diet templates fetched successfully.',
        );
    }

    public function assignTemplate(Request $request, DietPlanTemplate $dietPlanTemplate)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        foreach ($data['member_ids'] as $memberId) {
            $this->trainerScopeService->resolveAssignedMember(
                $profile,
                User::query()->findOrFail($memberId),
            );
        }

        $templatePayload = $this->dietPlanTemplateService->planPayload($dietPlanTemplate);
        $payload = array_merge($templatePayload, array_filter([
            'name' => $data['name'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ], static fn ($value) => $value !== null && $value !== ''), [
            'gym_id' => $profile->gym_id,
            'branch_id' => $profile->branch_id,
            'member_ids' => $data['member_ids'],
            'status' => 'active',
        ]);
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
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        if ((int) $plan->trainer_id !== (int) $request->user()->id || (int) $plan->gym_id !== (int) $profile->gym_id || ($profile->branch_id && (int) $plan->branch_id !== (int) $profile->branch_id)) {
            throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
        }
    }
}
