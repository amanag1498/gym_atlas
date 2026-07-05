<?php

namespace App\Http\Controllers\Api\Gym\Billing;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreMembershipPlanRequest;
use App\Http\Requests\Billing\UpdateMembershipPlanRequest;
use App\Http\Resources\Billing\MembershipPlanResource;
use App\Models\MembershipPlan;
use App\Services\Audit\AuditLogService;
use App\Services\Billing\BillingAccessService;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MembershipPlanController extends Controller
{
    public function __construct(
        private readonly BillingAccessService $billingAccessService,
        private readonly ScopeResolver $scopeResolver,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', MembershipPlan::class);

        $query = MembershipPlan::query()
            ->with('branch')
            ->withCount('memberMemberships')
            ->when($request->filled('gym_id'), fn ($builder) => $builder->where('gym_id', $request->integer('gym_id')))
            ->when($request->filled('branch_id'), function (Builder $builder) use ($request): void {
                $branchId = $request->integer('branch_id');
                $builder->where(function (Builder $query) use ($branchId): void {
                    $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($builder) => $builder->where('name', 'like', '%'.$request->string('search')->trim().'%'))
            ->orderBy('name');

        $query->whereIn('gym_id', $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id'));

        if ($request->user()->active_role !== RoleName::GymOwner->value) {
            $accessibleBranchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id');
            $query->where(function ($builder) use ($accessibleBranchIds): void {
                $builder->whereNull('branch_id')
                    ->orWhereIn('branch_id', $accessibleBranchIds);
            });
        }

        $plans = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($plans, MembershipPlanResource::collection($plans->getCollection()), 'Membership plans fetched successfully.');
    }

    public function store(StoreMembershipPlanRequest $request)
    {
        $this->authorize('create', MembershipPlan::class);

        $validated = $request->validated();
        $this->assertManageScope($request, $validated['branch_id'] ?? null);
        $this->billingAccessService->assertGymAccess($request->user(), $validated['gym_id']);
        $this->billingAccessService->assertBranchAccess($request->user(), $validated['gym_id'], $validated['branch_id'] ?? null);

        $plan = MembershipPlan::query()->create($validated + [
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->auditLogService->log(
            event: 'membership_plan.created',
            action: 'create',
            request: $request,
            subject: $plan,
            gym: $plan->gym,
            branch: $plan->branch,
            newValues: $plan->fresh()->toArray(),
        );

        return $this->success(
            MembershipPlanResource::make($plan->load('branch')->loadCount('memberMemberships')),
            'Membership plan created successfully.',
            201
        );
    }

    public function show(MembershipPlan $membershipPlan, Request $request)
    {
        $this->authorize('view', $membershipPlan);
        $this->billingAccessService->assertGymAccess($request->user(), $membershipPlan->gym_id);
        $this->billingAccessService->assertBranchAccess($request->user(), $membershipPlan->gym_id, $membershipPlan->branch_id);

        return $this->success(MembershipPlanResource::make($membershipPlan->load('branch')->loadCount('memberMemberships')));
    }

    public function update(UpdateMembershipPlanRequest $request, MembershipPlan $membershipPlan)
    {
        $this->authorize('update', $membershipPlan);

        $validated = $request->validated();
        $gymId = $validated['gym_id'] ?? $membershipPlan->gym_id;
        $branchId = array_key_exists('branch_id', $validated) ? $validated['branch_id'] : $membershipPlan->branch_id;

        $this->assertManageScope($request, $branchId);
        $this->billingAccessService->assertGymAccess($request->user(), $gymId);
        $this->billingAccessService->assertBranchAccess($request->user(), $gymId, $branchId);

        $oldValues = $membershipPlan->toArray();
        $membershipPlan->update($validated + ['gym_id' => $gymId]);

        $this->auditLogService->log(
            event: 'membership_plan.updated',
            action: 'update',
            request: $request,
            subject: $membershipPlan,
            gym: $membershipPlan->gym,
            branch: $membershipPlan->branch,
            oldValues: $oldValues,
            newValues: $membershipPlan->fresh()->toArray(),
        );

        return $this->success(
            MembershipPlanResource::make($membershipPlan->fresh()->load('branch')->loadCount('memberMemberships')),
            'Membership plan updated successfully.'
        );
    }

    public function activate(Request $request, MembershipPlan $membershipPlan)
    {
        return $this->updateStatus($request, $membershipPlan, 'active', 'Membership plan activated successfully.');
    }

    public function deactivate(Request $request, MembershipPlan $membershipPlan)
    {
        return $this->updateStatus($request, $membershipPlan, 'inactive', 'Membership plan deactivated successfully.');
    }

    private function updateStatus(Request $request, MembershipPlan $membershipPlan, string $status, string $message)
    {
        $this->authorize('update', $membershipPlan);
        $this->assertManageScope($request, $membershipPlan->branch_id);
        $this->billingAccessService->assertGymAccess($request->user(), $membershipPlan->gym_id);
        $this->billingAccessService->assertBranchAccess($request->user(), $membershipPlan->gym_id, $membershipPlan->branch_id);

        $oldValues = $membershipPlan->only(['status']);
        $membershipPlan->update(['status' => $status]);

        $this->auditLogService->log(
            event: 'membership_plan.status.updated',
            action: 'update',
            request: $request,
            subject: $membershipPlan,
            gym: $membershipPlan->gym,
            branch: $membershipPlan->branch,
            oldValues: $oldValues,
            newValues: $membershipPlan->fresh()->only(['status']),
        );

        return $this->success(
            MembershipPlanResource::make($membershipPlan->fresh()->load('branch')->loadCount('memberMemberships')),
            $message
        );
    }

    private function assertManageScope(Request $request, ?int $branchId): void
    {
        if ($request->user()?->active_role !== RoleName::GymOwner->value && $branchId === null) {
            throw new HttpException(403, 'Branch-scoped staff can only manage branch-specific membership plans.');
        }
    }
}
