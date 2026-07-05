<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreMembershipPlanRequest;
use App\Http\Requests\Billing\UpdateMembershipPlanRequest;
use App\Models\MembershipPlan;
use App\Services\Audit\AuditLogService;
use App\Services\Billing\BillingAccessService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MembershipPlanController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly BillingAccessService $billingAccessService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipPlansView->value, $gym);

        $query = $this->scopedQuery($request, $gym)
            ->with('branch')
            ->withCount('memberMemberships')
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where('name', 'like', $search);
        }

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');

            $query->where(function (Builder $builder) use ($branchId): void {
                $builder->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        $plans = $query->paginate(12)->withQueryString();

        return view('web.gym.membership-plans.index', [
            'pageTitle' => 'Membership Plans',
            'breadcrumbs' => ['Gym', 'Membership Plans'],
            'gym' => $gym,
            'plans' => $plans,
            'branches' => $this->accessibleBranches($request, $gym),
            'canManagePlans' => $this->gymWebPanelService->canPermission($request, PermissionName::MembershipPlansManage->value, $gym),
            'selectedScopeBranch' => $this->gymWebPanelService->resolveBranch($request, $gym),
        ]);
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipPlansManage->value, $gym);

        return view('web.gym.membership-plans.create', [
            'pageTitle' => 'Create Membership Plan',
            'breadcrumbs' => ['Gym', 'Membership Plans', 'Create'],
            'gym' => $gym,
            'branches' => $this->accessibleBranches($request, $gym),
            'selectedScopeBranch' => $this->gymWebPanelService->resolveBranch($request, $gym),
            'branchScopeRequired' => $request->user()?->active_role !== RoleName::GymOwner->value,
        ]);
    }

    public function store(StoreMembershipPlanRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branchId = $request->validated('branch_id');

        $this->assertManageScope($request, $gym, $branchId);
        $this->billingAccessService->assertGymAccess($request->user(), $gym->id);
        $this->billingAccessService->assertBranchAccess($request->user(), $gym->id, $branchId);

        $plan = MembershipPlan::query()->create($request->validated() + [
            'gym_id' => $gym->id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->auditLogService->log(
            event: 'web.gym.membership_plan.created',
            action: 'create',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            newValues: $plan->fresh()->toArray(),
        );

        return redirect()
            ->route('web.gym.membership-plans.show', ['plan' => $plan->id, 'gym' => $gym->id])
            ->with('status', 'Membership plan created successfully.');
    }

    public function show(Request $request, MembershipPlan $plan): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipPlansView->value, $gym);
        $this->assertPlanAccessible($request, $gym, $plan);

        $plan->load(['branch', 'creator']);
        $plan->loadCount('memberMemberships');

        return view('web.gym.membership-plans.show', [
            'pageTitle' => $plan->name,
            'breadcrumbs' => ['Gym', 'Membership Plans', $plan->name],
            'gym' => $gym,
            'plan' => $plan,
            'recentMemberships' => $plan->memberMemberships()
                ->with(['member', 'branch'])
                ->latest('id')
                ->take(8)
                ->get(),
            'canManagePlans' => $this->canManagePlan($request, $gym, $plan),
        ]);
    }

    public function edit(Request $request, MembershipPlan $plan): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertPlanAccessible($request, $gym, $plan);
        $this->assertManageScope($request, $gym, $plan->branch_id);

        return view('web.gym.membership-plans.edit', [
            'pageTitle' => 'Edit Membership Plan',
            'breadcrumbs' => ['Gym', 'Membership Plans', $plan->name, 'Edit'],
            'gym' => $gym,
            'plan' => $plan->load('branch'),
            'branches' => $this->accessibleBranches($request, $gym),
            'selectedScopeBranch' => $this->gymWebPanelService->resolveBranch($request, $gym),
            'branchScopeRequired' => $request->user()?->active_role !== RoleName::GymOwner->value,
        ]);
    }

    public function update(UpdateMembershipPlanRequest $request, MembershipPlan $plan): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertPlanAccessible($request, $gym, $plan);

        $validated = $request->validated();
        $branchId = array_key_exists('branch_id', $validated) ? $validated['branch_id'] : $plan->branch_id;

        $this->assertManageScope($request, $gym, $branchId);
        $this->billingAccessService->assertGymAccess($request->user(), $gym->id);
        $this->billingAccessService->assertBranchAccess($request->user(), $gym->id, $branchId);

        $oldValues = $plan->toArray();
        $plan->update($validated + ['gym_id' => $gym->id]);

        $this->auditLogService->log(
            event: 'web.gym.membership_plan.updated',
            action: 'update',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            oldValues: $oldValues,
            newValues: $plan->fresh()->toArray(),
        );

        return redirect()
            ->route('web.gym.membership-plans.show', ['plan' => $plan->id, 'gym' => $gym->id])
            ->with('status', 'Membership plan updated successfully.');
    }

    public function activate(Request $request, MembershipPlan $plan): RedirectResponse
    {
        return $this->updateStatus($request, $plan, 'active', 'Membership plan activated successfully.');
    }

    public function deactivate(Request $request, MembershipPlan $plan): RedirectResponse
    {
        return $this->updateStatus($request, $plan, 'inactive', 'Membership plan deactivated successfully.');
    }

    private function updateStatus(Request $request, MembershipPlan $plan, string $status, string $message): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertPlanAccessible($request, $gym, $plan);
        $this->assertManageScope($request, $gym, $plan->branch_id);

        $oldValues = $plan->only(['status']);
        $plan->update(['status' => $status]);

        $this->auditLogService->log(
            event: 'web.gym.membership_plan.status.updated',
            action: 'update',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            oldValues: $oldValues,
            newValues: $plan->fresh()->only(['status']),
        );

        return back()->with('status', $message);
    }

    private function scopedQuery(Request $request, $gym): Builder
    {
        $query = MembershipPlan::query()->where('gym_id', $gym->id);

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $query->where(function (Builder $builder) use ($branch): void {
                $builder->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            });
        } elseif ($request->user()?->active_role !== RoleName::GymOwner->value) {
            $branchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
            $query->where(function (Builder $builder) use ($branchIds): void {
                $builder->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            });
        }

        return $query;
    }

    private function assertPlanAccessible(Request $request, $gym, MembershipPlan $plan): void
    {
        abort_unless($plan->gym_id === $gym->id, 404);

        if ($plan->branch_id === null) {
            return;
        }

        $accessibleBranchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
        abort_unless(in_array($plan->branch_id, $accessibleBranchIds, true), 403);
    }

    private function canManagePlan(Request $request, $gym, MembershipPlan $plan): bool
    {
        if ($request->user()?->active_role !== RoleName::GymOwner->value && $plan->branch_id === null) {
            return false;
        }

        return $this->gymWebPanelService->canPermission($request, PermissionName::MembershipPlansManage->value, $gym, $plan->branch_id);
    }

    private function assertManageScope(Request $request, $gym, ?int $branchId): void
    {
        if ($request->user()?->active_role !== RoleName::GymOwner->value && $branchId === null) {
            throw new HttpException(403, 'Branch-scoped staff can only manage branch-specific membership plans.');
        }

        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipPlansManage->value, $gym, $branchId);
    }

    /**
     * @return Collection<int, \App\Models\Branch>
     */
    private function accessibleBranches(Request $request, $gym): Collection
    {
        return $this->gymWebPanelService->accessibleBranches($request, $gym)->sortBy('name')->values();
    }
}
