<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diet\SaveDietPlanTemplateRequest;
use App\Http\Requests\Diet\StoreTrainerDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Http\Resources\Diet\DietPlanResource;
use App\Http\Resources\Diet\DietPlanTemplateResource;
use App\Models\DietPlan;
use App\Models\DietPlanTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Diet\DietPlanService;
use App\Services\Diet\DietPlanTemplateService;
use App\Services\Trainer\IndependentCoachingAccessService;
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
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
    ) {}

    public function index(Request $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $activeRelationshipIds = $profile->gym_id === null
            && $this->independentCoachingAccessService->isVerifiedIndependentTrainer($request->user())
            ? $this->independentCoachingAccessService
                ->activeRelationshipsForTrainer($request->user())
                ->get(['id', 'sharing_permissions'])
                ->filter(fn ($relationship): bool => in_array('diets', $relationship->sharing_permissions ?? [], true))
                ->pluck('id')
            : collect();
        $memberId = $request->integer('member_id') ?: null;
        if ($memberId) {
            $member = User::query()->findOrFail($memberId);
            $profile->gym_id === null
                ? $this->independentCoachingAccessService->resolveActiveRelationship($request->user(), $member, null, 'diets')
                : $this->trainerScopeService->resolveAssignedMember($profile, $member);
        }

        $paginator = DietPlan::query()
            ->with(['member', 'trainer', 'meals.items'])
            ->where('trainer_id', $request->user()->id)
            ->where('gym_id', $profile->gym_id)
            ->where('status', 'active')
            ->when($profile->branch_id, fn ($query) => $query->where('branch_id', $profile->branch_id))
            ->when(
                $profile->gym_id === null,
                fn ($query) => $query->whereIn('independent_trainer_member_relationship_id', $activeRelationshipIds),
            )
            ->when($memberId, fn ($query, int $id) => $query->where('member_id', $id))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return $this->paginated($paginator, DietPlanResource::collection($paginator->getCollection()), 'Diet plans fetched successfully.');
    }

    public function store(StoreTrainerDietPlanRequest $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        if ($profile->gym_id !== null) {
            foreach ($request->validated('member_ids') as $id) {
                $this->trainerScopeService->resolveAssignedMember($profile, User::query()->findOrFail($id));
            }
        }
        $data = $request->validated();
        $data['gym_id'] = $profile->gym_id;
        $data['branch_id'] = $profile->branch_id;
        if ($profile->gym_id === null) {
            if (count($data['member_ids']) !== 1) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Assign an independent diet plan to one member at a time.'],
                ]);
            }
            $member = User::query()->findOrFail((int) $data['member_ids'][0]);
            $relationship = $this->independentCoachingAccessService->resolveActiveRelationship(
                $request->user(),
                $member,
                isset($data['independent_trainer_member_relationship_id'])
                    ? (int) $data['independent_trainer_member_relationship_id']
                    : null,
                'diets',
            );
            $data['independent_trainer_member_relationship_id'] = $relationship->id;
        } else {
            $data['independent_trainer_member_relationship_id'] = null;
        }
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
            ->where(function ($query) use ($request): void {
                $query
                    ->where('created_by_user_id', $request->user()->id)
                    ->orWhereNull('created_by_user_id')
                    ->orWhereHas(
                        'creator.roles',
                        fn ($roles) => $roles->where(
                            'name',
                            RoleName::PlatformAdmin->value,
                        ),
                    );
            })
            ->where(function ($query) use ($request): void {
                $query
                    ->where('status', 'active')
                    ->orWhere('created_by_user_id', $request->user()->id);
            })
            ->orderBy('name')
            ->get();

        return $this->success(
            DietPlanTemplateResource::collection($templates),
            'Global diet templates fetched successfully.',
        );
    }

    public function storeTemplate(SaveDietPlanTemplateRequest $request)
    {
        $this->trainerScopeService->resolveTrainerProfile($request);
        $template = DietPlanTemplate::query()->create(
            $request->validated()
            + ['created_by_user_id' => $request->user()->id]
        );

        $this->auditLogService->log(
            event: 'trainer.diet_template.created',
            action: 'create',
            request: $request,
            subject: $template,
            newValues: $template->toArray(),
        );

        return $this->success(
            DietPlanTemplateResource::make($template),
            'Diet template created successfully.',
            201,
        );
    }

    public function updateTemplate(
        SaveDietPlanTemplateRequest $request,
        DietPlanTemplate $dietPlanTemplate,
    ) {
        $this->trainerScopeService->resolveTrainerProfile($request);
        $this->assertTemplateOwnership($request, $dietPlanTemplate);
        $oldValues = $dietPlanTemplate->toArray();
        $dietPlanTemplate->update($request->validated());

        $this->auditLogService->log(
            event: 'trainer.diet_template.updated',
            action: 'update',
            request: $request,
            subject: $dietPlanTemplate,
            oldValues: $oldValues,
            newValues: $dietPlanTemplate->fresh()->toArray(),
        );

        return $this->success(
            DietPlanTemplateResource::make($dietPlanTemplate->fresh()),
            'Diet template updated successfully.',
        );
    }

    public function destroyTemplate(
        Request $request,
        DietPlanTemplate $dietPlanTemplate,
    ) {
        $this->trainerScopeService->resolveTrainerProfile($request);
        $this->assertTemplateOwnership($request, $dietPlanTemplate);
        $oldValues = $dietPlanTemplate->toArray();
        $dietPlanTemplate->delete();

        $this->auditLogService->log(
            event: 'trainer.diet_template.deleted',
            action: 'delete',
            request: $request,
            subject: $dietPlanTemplate,
            oldValues: $oldValues,
        );

        return $this->success(null, 'Diet template deleted successfully.');
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
        if ($profile->gym_id !== null) {
            foreach ($data['member_ids'] as $memberId) {
                $this->trainerScopeService->resolveAssignedMember(
                    $profile,
                    User::query()->findOrFail($memberId),
                );
            }
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
        if ($profile->gym_id === null) {
            if (count($data['member_ids']) !== 1) {
                throw ValidationException::withMessages([
                    'member_ids' => ['Assign an independent diet plan to one member at a time.'],
                ]);
            }
            $member = User::query()->findOrFail((int) $data['member_ids'][0]);
            $relationship = $this->independentCoachingAccessService->resolveActiveRelationship(
                $request->user(),
                $member,
                null,
                'diets',
            );
            $payload['independent_trainer_member_relationship_id'] = $relationship->id;
        }
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
        if ($plan->independent_trainer_member_relationship_id !== null) {
            if ((int) $plan->trainer_id !== (int) $request->user()->id) {
                throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
            }
            $this->independentCoachingAccessService->resolveActiveRelationship(
                $request->user(),
                $plan->member,
                (int) $plan->independent_trainer_member_relationship_id,
                'diets',
            );

            return;
        }
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages([
                'diet_plan_id' => ['This gym diet plan is historical and cannot be changed as a current assignment.'],
            ]);
        }
        $this->trainerScopeService->resolveAssignedMember($profile, $plan->member);
        if ((int) $plan->trainer_id !== (int) $request->user()->id || (int) $plan->gym_id !== (int) $profile->gym_id || ($profile->branch_id && (int) $plan->branch_id !== (int) $profile->branch_id)) {
            throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
        }
    }

    private function assertTemplateOwnership(
        Request $request,
        DietPlanTemplate $template,
    ): void {
        if ((int) $template->created_by_user_id !== (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'diet_template_id' => ['You can only change your own diet templates.'],
            ]);
        }
    }
}
