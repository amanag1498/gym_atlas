<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DietPlan;
use App\Models\DietPlanTemplate;
use App\Models\FoodCatalogItem;
use App\Models\MemberProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Diet\DietPlanService;
use App\Services\Diet\DietPlanTemplateService;
use App\Services\Members\GymMemberAccessService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DietPlanController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly DietPlanService $dietPlanService,
        private readonly DietPlanTemplateService $dietPlanTemplateService,
        private readonly AuditLogService $auditLogService,
        private readonly GymMemberAccessService $gymMemberAccessService,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::DietPlansView->value, $gym);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym);

        $plans = DietPlan::query()
            ->with(['member', 'trainer', 'branch', 'meals.items'])
            ->where('gym_id', $gym->id)
            ->whereHas('member.memberProfiles', function ($profile) use ($gym): void {
                $profile->where('gym_id', $gym->id);
                $this->gymMemberAccessService->scopeAccessibleProfiles($profile);
            })
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $members = MemberProfile::query()
            ->with('user')
            ->where('gym_id', $gym->id)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->tap(fn ($query) => $this->gymMemberAccessService->scopeAccessibleProfiles($query))
            ->orderBy('user_id')
            ->get();

        return view('web.gym.diet-plans.index', [
            'pageTitle' => 'Diet Plans',
            'breadcrumbs' => ['Gym', 'Diet Plans'],
            'gym' => $gym,
            'branch' => $branch,
            'plans' => $plans,
            'members' => $members,
            'templates' => DietPlanTemplate::query()->globalCatalog()->where('status', 'active')->orderBy('name')->get(),
            'foodCatalog' => FoodCatalogItem::query()->active()->orderBy('category')->orderBy('name')->get(),
            'canManageDietPlans' => $this->gymWebPanelService->canPermission($request, PermissionName::DietPlansManage->value, $gym, $branch?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym);
        $this->gymWebPanelService->assertPermission($request, PermissionName::DietPlansManage->value, $gym, $branch?->id);

        if ($request->filled('diet_template_id')) {
            $customName = $request->string('name')->trim()->toString();
            $template = DietPlanTemplate::query()
                ->globalCatalog()
                ->where('status', 'active')
                ->findOrFail($request->integer('diet_template_id'));

            $request->merge($this->dietPlanTemplateService->planPayload($template));
            if ($customName !== '') {
                $request->merge(['name' => $customName]);
            }
        }

        $this->normalizeMeals($request);

        $data = $request->validate([
            'diet_template_id' => [
                'nullable',
                'integer',
                Rule::exists('diet_plan_templates', 'id')->where('status', 'active'),
            ],
            'member_id' => ['required', 'integer'],
        ] + $this->planRules());

        $memberExists = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->where('user_id', $data['member_id']);
        $this->gymMemberAccessService->scopeAccessibleProfiles($memberExists);
        $memberExists = $memberExists->exists();
        abort_unless($memberExists, 404);

        $plans = $this->dietPlanService->create($request->user(), $data + [
            'gym_id' => $gym->id,
            'branch_id' => $branch?->id,
            'member_ids' => [$data['member_id']],
            'status' => 'active',
        ]);
        $plan = $plans[0];
        $this->auditLogService->log(event: 'web.gym.diet_plan.created', action: 'create', request: $request, subject: $plan, gym: $gym, branch: $branch, newValues: $plan->toArray());

        return redirect()->route('web.gym.diet-plans.index', request()->only(['gym', 'branch']))
            ->with('status', 'Diet plan assigned successfully.');
    }

    public function edit(Request $request, DietPlan $dietPlan): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless((int) $dietPlan->gym_id === (int) $gym->id, 404);
        $this->assertActivePlanMember($dietPlan);
        $this->gymWebPanelService->assertPermission(
            $request,
            PermissionName::DietPlansManage->value,
            $gym,
            $dietPlan->branch_id,
        );

        return view('web.gym.diet-plans.edit', [
            'pageTitle' => 'Edit Diet Plan',
            'breadcrumbs' => ['Gym', 'Diet Plans', $dietPlan->name],
            'gym' => $gym,
            'branch' => $dietPlan->branch,
            'plan' => $dietPlan->load(['member', 'meals.items']),
            'foodCatalog' => FoodCatalogItem::query()->active()->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, DietPlan $dietPlan): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless((int) $dietPlan->gym_id === (int) $gym->id, 404);
        $this->assertActivePlanMember($dietPlan);
        $this->gymWebPanelService->assertPermission(
            $request,
            PermissionName::DietPlansManage->value,
            $gym,
            $dietPlan->branch_id,
        );
        $this->normalizeMeals($request);

        $oldValues = $dietPlan->load('meals.items')->toArray();
        $plan = $this->dietPlanService->update(
            $dietPlan,
            $request->user(),
            $request->validate($this->planRules() + [
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ]),
        );

        $this->auditLogService->log(
            event: 'web.gym.diet_plan.updated',
            action: 'update',
            request: $request,
            subject: $plan,
            gym: $gym,
            branch: $plan->branch,
            oldValues: $oldValues,
            newValues: $plan->toArray(),
        );

        return redirect()
            ->route(
                'web.gym.diet-plans.index',
                array_filter([
                    'gym' => $gym->id,
                    'branch' => $dietPlan->branch_id,
                ])
            )
            ->with('status', 'Diet plan updated successfully.');
    }

    public function updateStatus(Request $request, DietPlan $dietPlan): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($dietPlan->gym_id === $gym->id, 404);
        $this->assertActivePlanMember($dietPlan);
        $this->gymWebPanelService->assertPermission($request, PermissionName::DietPlansManage->value, $gym, $dietPlan->branch_id);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]])['status'];
        $oldValues = $dietPlan->only(['status']);
        $dietPlan->update(['status' => $status]);
        $this->auditLogService->log(event: 'web.gym.diet_plan.status_updated', action: 'update', request: $request, subject: $dietPlan, gym: $gym, branch: $dietPlan->branch, oldValues: $oldValues, newValues: $dietPlan->only(['status']));

        return back()->with('status', 'Diet plan status updated.');
    }

    private function assertActivePlanMember(DietPlan $dietPlan): void
    {
        $profile = MemberProfile::query()
            ->where('user_id', $dietPlan->member_id)
            ->where('gym_id', $dietPlan->gym_id)
            ->firstOrFail();
        $this->gymMemberAccessService->assertAccessible($profile);
    }

    private function normalizeMeals(Request $request): void
    {
        $request->merge([
            'meals' => collect($request->input('meals', []))
                ->filter(fn ($meal) => is_array($meal))
                ->map(function (array $meal): array {
                    $meal['items'] = collect($meal['items'] ?? [])
                        ->filter(
                            fn ($item): bool => is_array($item)
                                && filled($item['name'] ?? null)
                        )
                        ->values()
                        ->all();

                    return $meal;
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function planRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'daily_calorie_target' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'protein_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'carbs_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'fats_target_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'dietary_preferences' => ['nullable', 'string', 'max:4000'],
            'allergies_and_restrictions' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:6000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'meals' => ['required', 'array', 'min:1', 'max:12'],
            'meals.*.id' => ['nullable', 'integer'],
            'meals.*.name' => ['required', 'string', 'max:255'],
            'meals.*.meal_type' => ['nullable', 'string', 'max:80'],
            'meals.*.scheduled_time' => ['nullable', 'date_format:H:i'],
            'meals.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.fats_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.notes' => ['nullable', 'string', 'max:2000'],
            'meals.*.items' => ['nullable', 'array', 'max:30'],
            'meals.*.items.*.id' => ['nullable', 'integer'],
            'meals.*.items.*.food_catalog_item_id' => [
                'nullable',
                'integer',
                Rule::exists('food_catalog_items', 'id'),
            ],
            'meals.*.items.*.name' => ['required', 'string', 'max:255'],
            'meals.*.items.*.quantity' => ['nullable', 'string', 'max:120'],
            'meals.*.items.*.calories' => ['nullable', 'integer', 'min:0'],
            'meals.*.items.*.protein_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.carbs_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.fats_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.fiber_g' => ['nullable', 'numeric', 'min:0'],
            'meals.*.items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
