<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Diet\DietPlanResource;
use App\Http\Requests\Diet\StoreMemberDietPlanRequest;
use App\Http\Requests\Diet\UpdateDietPlanRequest;
use App\Models\DietMealLog;
use App\Models\DietPlan;
use App\Models\DietPlanMeal;
use App\Services\Audit\AuditLogService;
use App\Services\Diet\DietPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietPlanController extends Controller
{
    public function __construct(
        private readonly DietPlanService $dietPlanService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $profile = $request->user()->memberProfile;
        if (! $profile?->gym_id) {
            return $this->success([], 'No active gym space is available.');
        }
        $plans = DietPlan::query()
            ->with([
                'meals.items',
                'meals.logs' => fn ($query) => $query->where('member_id', $request->user()->id)->whereDate('logged_for', today()),
            ])
            ->where('member_id', $request->user()->id)
            ->where('gym_id', $profile->gym_id)
            ->when($profile->branch_id, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $profile->branch_id)))
            ->availableOn()
            ->latest()
            ->get();

        return $this->success(DietPlanResource::collection($plans), 'Diet plans fetched successfully.');
    }

    public function show(Request $request, DietPlan $dietPlan)
    {
        $this->assertOwner($request, $dietPlan);
        $this->assertAvailable($dietPlan, today()->toDateString());

        return $this->success(DietPlanResource::make($dietPlan->load([
            'meals.items',
            'meals.logs' => fn ($query) => $query->where('member_id', $request->user()->id)->whereDate('logged_for', today()),
        ])));
    }

    public function store(StoreMemberDietPlanRequest $request)
    {
        $profile = $request->user()->memberProfile;
        if (! $profile?->gym_id) {
            throw ValidationException::withMessages(['diet_plan' => ['Join a gym before creating a personal diet plan.']]);
        }

        $plans = $this->dietPlanService->create($request->user(), $request->validated() + [
            'gym_id' => $profile->gym_id,
            'branch_id' => $profile->branch_id,
            'member_ids' => [$request->user()->id],
            'status' => $request->validated('status', 'active'),
        ]);
        $plan = $plans[0];
        $this->auditLogService->log(event: 'member.diet_plan.created', action: 'create', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, newValues: $plan->toArray());

        return $this->success(DietPlanResource::make($plan), 'Personal diet plan created successfully.', 201);
    }

    public function update(UpdateDietPlanRequest $request, DietPlan $dietPlan)
    {
        $this->assertMemberManagedPlan($request, $dietPlan);
        $oldValues = $dietPlan->load('meals.items')->toArray();
        $plan = $this->dietPlanService->update($dietPlan, $request->user(), $request->validated());
        $this->auditLogService->log(event: 'member.diet_plan.updated', action: 'update', request: $request, subject: $plan, gym: $plan->gym, branch: $plan->branch, oldValues: $oldValues, newValues: $plan->toArray());

        return $this->success(DietPlanResource::make($plan), 'Personal diet plan updated successfully.');
    }

    public function destroy(Request $request, DietPlan $dietPlan)
    {
        $this->assertMemberManagedPlan($request, $dietPlan);
        $oldValues = $dietPlan->load('meals.items')->toArray();
        $this->auditLogService->log(event: 'member.diet_plan.deleted', action: 'delete', request: $request, subject: $dietPlan, gym: $dietPlan->gym, branch: $dietPlan->branch, oldValues: $oldValues);
        $dietPlan->delete();

        return $this->success(null, 'Personal diet plan deleted successfully.');
    }

    public function logMeal(Request $request, DietPlan $dietPlan, DietPlanMeal $meal)
    {
        $this->assertOwner($request, $dietPlan);
        abort_unless($meal->diet_plan_id === $dietPlan->id, 404);
        $data = $request->validate(['logged_for' => ['nullable', 'date'], 'completed' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $loggedFor = $data['logged_for'] ?? today()->toDateString();
        $this->assertAvailable($dietPlan, $loggedFor);
        $log = DietMealLog::query()->firstOrNew(['diet_plan_meal_id' => $meal->id, 'member_id' => $request->user()->id, 'logged_for' => $loggedFor]);
        $log->fill(['completed_at' => ($data['completed'] ?? true) ? now() : null, 'notes' => $data['notes'] ?? null])->save();

        return $this->success(['id' => $log->id, 'completed_at' => $log->completed_at?->toIso8601String(), 'logged_for' => $log->logged_for?->toDateString()], 'Meal progress updated.');
    }

    private function assertOwner(Request $request, DietPlan $plan): void
    {
        $profile = $request->user()->memberProfile;
        if ((int) $plan->member_id !== (int) $request->user()->id || ! $profile?->gym_id || (int) $plan->gym_id !== (int) $profile->gym_id || ($plan->branch_id && (int) $plan->branch_id !== (int) $profile->branch_id)) {
            throw ValidationException::withMessages(['diet_plan_id' => ['You do not have access to this diet plan.']]);
        }
    }

    private function assertMemberManagedPlan(Request $request, DietPlan $plan): void
    {
        $this->assertOwner($request, $plan);
        if ((int) $plan->created_by_user_id !== (int) $request->user()->id || $plan->trainer_id !== null) {
            throw ValidationException::withMessages(['diet_plan_id' => ['Only your personal diet plans can be changed.']]);
        }
    }

    private function assertAvailable(DietPlan $plan, string $date): void
    {
        if (! DietPlan::query()->whereKey($plan->id)->availableOn($date)->exists()) {
            throw ValidationException::withMessages(['diet_plan_id' => ['This diet plan is not active for the selected date.']]);
        }
    }
}
