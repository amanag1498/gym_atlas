<?php

namespace App\Http\Controllers\Api\Gym\Billing;

use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ExtendMembershipRequest;
use App\Http\Requests\Billing\RenewMembershipRequest;
use App\Http\Requests\Billing\StoreMemberMembershipRequest;
use App\Http\Requests\Billing\UpdateCustomFeeRequest;
use App\Http\Requests\Billing\UpdateMembershipLifecycleRequest;
use App\Http\Resources\Audit\ActivityLogResource;
use App\Http\Resources\Billing\CustomFeeAuditLogResource;
use App\Http\Resources\Billing\MemberMembershipResource;
use App\Models\ActivityLog;
use App\Models\CustomFeeAuditLog;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\AuditTimelineService;
use App\Services\Billing\BillingAccessService;
use App\Services\Billing\CustomFeeAuditService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\MembershipEnrollmentService;
use App\Services\Billing\MembershipPricingService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Notification\ReminderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberMembershipController extends Controller
{
    public function __construct(
        private readonly BillingAccessService $billingAccessService,
        private readonly MembershipPricingService $membershipPricingService,
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly CustomFeeAuditService $customFeeAuditService,
        private readonly AuditLogService $auditLogService,
        private readonly AuditTimelineService $auditTimelineService,
        private readonly ScopeResolver $scopeResolver,
        private readonly ReminderService $reminderService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', MemberMembership::class);

        $query = MemberMembership::query()
            ->with(['member', 'membershipPlan'])
            ->when($request->filled('gym_id'), fn ($builder) => $builder->where('gym_id', $request->integer('gym_id')))
            ->when($request->filled('branch_id'), fn ($builder) => $builder->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('member_id'), fn ($builder) => $builder->where('member_id', $request->integer('member_id')))
            ->when($request->filled('plan_id'), fn ($builder) => $builder->where('membership_plan_id', $request->integer('plan_id')))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('status'), function (Builder $builder) use ($request): void {
                $status = $request->string('status')->toString();

                if ($status === 'expiring-soon') {
                    $builder->where('status', MembershipStatus::Active->value)
                        ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);

                    return;
                }

                if ($status === 'expired') {
                    $builder->where(function (Builder $query): void {
                        $query->where('status', MembershipStatus::Expired->value)
                            ->orWhere(function (Builder $nested): void {
                                $nested->where('status', MembershipStatus::Active->value)
                                    ->whereDate('expiry_date', '<', now()->toDateString());
                            });
                    });

                    return;
                }

                $builder->where('status', $status);
            })
            ->when($request->filled('member_search'), fn ($builder) => $builder->whereHas('member', function ($memberQuery) use ($request): void {
                $search = '%'.$request->string('member_search')->trim().'%';
                $memberQuery->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            }))
            ->orderByDesc('id');

        if ($request->boolean('due_amount')) {
            $query->where('due_amount', '>', 0);
        }

        if ($request->boolean('overdue')) {
            $query->where('payment_status', 'overdue');
        }

        $query->whereIn('gym_id', $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id'));

        if ($request->user()->active_role !== RoleName::GymOwner->value) {
            $query->whereIn('branch_id', $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id'));
        }

        $memberships = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($memberships, MemberMembershipResource::collection($memberships->getCollection()), 'Member memberships fetched successfully.');
    }

    public function customFeesIndex(Request $request)
    {
        $this->authorize('viewAny', MemberMembership::class);

        $query = $this->customFeeMembershipQuery($request)
            ->with(['member.memberProfile.branch', 'membershipPlan', 'branch', 'latestCustomFeeAuditLog.changer']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('plan_id')) {
            $query->where('membership_plan_id', $request->integer('plan_id'));
        }

        if ($request->boolean('discounted_only')) {
            $query->where(function (Builder $builder): void {
                $builder->where('discount_amount', '>', 0)
                    ->orWhere('joining_fee_waived', true);
            });
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        $memberships = $query->orderByDesc('id')->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $memberships,
            MemberMembershipResource::collection($memberships->getCollection()),
            'Custom fee memberships fetched successfully.'
        );
    }

    public function customFeeAuditLogs(Request $request)
    {
        $query = CustomFeeAuditLog::query()
            ->with(['member.memberProfile.branch', 'membership.membershipPlan', 'changer']);

        if ($request->filled('gym_id')) {
            $gym = $this->billingAccessService->assertGymAccess($request->user(), $request->integer('gym_id'));
            $query->where('gym_id', $gym->id);
        } else {
            $query->whereIn('gym_id', $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id'));
        }

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');
            $allowedBranchIds = $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id')->all();

            if (! in_array($branchId, $allowedBranchIds, true) && $request->user()->active_role !== RoleName::PlatformAdmin->value) {
                throw ValidationException::withMessages([
                    'branch_id' => ['You do not have access to the selected branch.'],
                ]);
            }

            $query->whereHas('membership', fn (Builder $builder) => $builder->where('branch_id', $branchId));
        } elseif (! in_array($request->user()->active_role, [RoleName::GymOwner->value, RoleName::PlatformAdmin->value], true)) {
            $query->whereHas('membership', fn (Builder $builder) => $builder->whereIn('branch_id', $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id')));
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        if ($request->filled('changed_by')) {
            $query->where('changed_by', $request->integer('changed_by'));
        }

        $logs = $query->latest('changed_at')->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $logs,
            CustomFeeAuditLogResource::collection($logs->getCollection()),
            'Custom fee audit logs fetched successfully.'
        );
    }

    public function store(StoreMemberMembershipRequest $request)
    {
        $this->authorize('create', MemberMembership::class);

        return $this->createMembershipFromPayload($request, $request->validated());
    }

    public function assignForMember(StoreMemberMembershipRequest $request, User $member)
    {
        $this->authorize('create', MemberMembership::class);

        $validated = $request->validated();
        $validated['member_id'] = $member->id;

        return $this->createMembershipFromPayload($request, $validated);
    }

    public function show(MemberMembership $memberMembership, Request $request)
    {
        $this->authorize('view', $memberMembership);
        $this->billingAccessService->assertMembershipAccess($request->user(), $memberMembership);
        $paymentIds = Payment::query()
            ->where('member_membership_id', $memberMembership->id)
            ->pluck('id')
            ->all();
        $activityLogs = ActivityLog::query()
            ->with('actor')
            ->where('gym_id', $memberMembership->gym_id)
            ->where('branch_id', $memberMembership->branch_id)
            ->where(function ($builder) use ($memberMembership, $paymentIds): void {
                $builder->where(fn ($query) => $query
                    ->where('subject_type', MemberMembership::class)
                    ->where('subject_id', $memberMembership->id));

                if ($paymentIds !== []) {
                    $builder->orWhere(fn ($query) => $query
                        ->where('subject_type', Payment::class)
                        ->whereIn('subject_id', $paymentIds));
                }
            })
            ->latest('occurred_at')
            ->take(12)
            ->get();
        $membership = $memberMembership->load(['member', 'membershipPlan', 'payments.receipt', 'customFeeAuditLogs.changer']);

        return $this->success([
            'member_membership' => MemberMembershipResource::make($membership),
            'activity_logs' => ActivityLogResource::collection($activityLogs),
            'activity_timeline' => $this->auditTimelineService->forActivityLogs($activityLogs),
            'custom_fee_timeline' => $this->auditTimelineService->forCustomFeeAudits($membership->customFeeAuditLogs),
        ]);
    }

    public function customFeeForMember(User $member, Request $request)
    {
        $memberships = $this->memberCustomFeeMemberships($request, $member)
            ->with(['membershipPlan', 'branch', 'payments', 'customFeeAuditLogs.changer'])
            ->get()
            ->each(function (MemberMembership $membership): void {
                $membership->setAttribute(
                    'custom_fee_timeline',
                    $this->auditTimelineService->forCustomFeeAudits($membership->customFeeAuditLogs)
                );
            });

        $canEdit = $memberships->contains(function (MemberMembership $membership) use ($request): bool {
            return $request->user()->can('updateCustomFee', $membership);
        });

        return $this->success([
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
            ],
            'memberships' => MemberMembershipResource::collection($memberships),
            'selected_membership_id' => (int) $request->integer('member_membership_id'),
            'can_edit_custom_fee' => $canEdit,
        ], 'Member custom fee details fetched successfully.');
    }

    public function updateCustomFeeForMember(UpdateCustomFeeRequest $request, User $member)
    {
        $membership = $this->resolveMemberCustomFeeMembership($request, $member);

        return $this->persistCustomFeeUpdate($request, $membership);
    }

    public function updateCustomFee(UpdateCustomFeeRequest $request, MemberMembership $memberMembership)
    {
        return $this->persistCustomFeeUpdate($request, $memberMembership);
    }

    public function renew(RenewMembershipRequest $request, MemberMembership $memberMembership)
    {
        $this->authorize('update', $memberMembership);
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);

        $result = DB::transaction(function () use ($request, $memberMembership) {
            $result = $this->membershipLifecycleService->renew($memberMembership, $request->user(), [
                'start_date' => $request->validated('start_date'),
                'due_date' => $request->validated('due_date'),
                'status' => $request->validated('status', MembershipStatus::Active->value),
                'amount_paid' => $request->validated('amount_paid', 0),
            ]);

            $renewed = $result['membership'];
            $initialPayment = $result['initial_payment'];

            $this->auditLogService->log(
                event: 'membership.renewed',
                action: 'create',
                request: $request,
                subject: $renewed,
                gym: $renewed->gym,
                branch: $renewed->branch,
                oldValues: ['renewed_from_membership_id' => $memberMembership->id],
                newValues: $renewed->toArray(),
                context: ['reason' => $request->validated('notes')],
            );

            if ($initialPayment) {
                $this->auditLogService->log(
                    event: 'payment.recorded',
                    action: 'create',
                    request: $request,
                    subject: $initialPayment,
                    gym: $renewed->gym,
                    branch: $renewed->branch,
                    newValues: $initialPayment->toArray(),
                    context: ['source' => 'membership_renewal'],
                );
            }

            $this->reminderService->syncMembershipReminders($renewed->fresh());

            return $renewed;
        });

        return $this->success(
            MemberMembershipResource::make($result->load(['member', 'membershipPlan'])),
            'Membership renewed successfully.',
            201
        );
    }

    public function freeze(UpdateMembershipLifecycleRequest $request, MemberMembership $memberMembership)
    {
        return $this->updateLifecycle(
            request: $request,
            memberMembership: $memberMembership,
            event: 'membership.frozen',
            message: 'Membership frozen successfully.',
            callback: fn () => $this->membershipLifecycleService->freeze($memberMembership),
        );
    }

    public function extend(ExtendMembershipRequest $request, MemberMembership $memberMembership)
    {
        return $this->updateLifecycle(
            request: $request,
            memberMembership: $memberMembership,
            event: 'membership.extended',
            message: 'Membership extended successfully.',
            callback: fn () => $this->membershipLifecycleService->extend(
                $memberMembership,
                $request->validated('extra_days'),
                $request->validated('due_date')
            ),
        );
    }

    public function cancel(UpdateMembershipLifecycleRequest $request, MemberMembership $memberMembership)
    {
        return $this->updateLifecycle(
            request: $request,
            memberMembership: $memberMembership,
            event: 'membership.cancelled',
            message: 'Membership cancelled successfully.',
            callback: fn () => $this->membershipLifecycleService->cancel($memberMembership),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createMembershipFromPayload(Request $request, array $validated)
    {
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $validated['gym_id']);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $validated['gym_id'], $validated['branch_id']);

        $plan = MembershipPlan::query()->findOrFail($validated['membership_plan_id']);
        $this->billingAccessService->assertPlanBelongsToScope($plan, $validated['gym_id'], $validated['branch_id']);

        $member = User::query()->findOrFail($validated['member_id']);

        if (! $member->hasRole('member')) {
            throw ValidationException::withMessages([
                'member_id' => ['The selected user must have the member role.'],
            ]);
        }

        $memberProfile = $member->memberProfile;

        if (! $memberProfile || (int) $memberProfile->gym_id !== (int) $validated['gym_id']) {
            throw ValidationException::withMessages([
                'member_id' => ['The selected member must belong to the same gym.'],
            ]);
        }

        if ($memberProfile->branch_id !== null && (int) $memberProfile->branch_id !== (int) $validated['branch_id']) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected member is not assigned to the selected branch.'],
            ]);
        }

        ['membership' => $membership, 'initial_payment' => $initialPayment] = $this->membershipEnrollmentService->enroll(
            $plan,
            $request->user(),
            [
                ...$validated,
                'status' => $validated['status'] ?? MembershipStatus::Active->value,
            ],
        );

        $this->membershipLifecycleService->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        if ($membership->custom_fee_enabled) {
            $newValues = $membership->only([
                'custom_fee_enabled',
                'custom_fee_amount',
                'discount_type',
                'discount_amount',
                'custom_joining_fee',
                'joining_fee_waived',
                'partial_month_fee',
                'pt_custom_fee',
                'final_payable_amount',
                'due_amount',
                'due_date',
            ]);

            $this->customFeeAuditService->log(
                $membership,
                $request->user(),
                [],
                $newValues,
                $membership->custom_fee_reason ?? 'Initial custom fee applied.'
            );
        }

        $this->auditLogService->log(
            event: 'membership.created',
            action: 'create',
            request: $request,
            subject: $membership,
            gym: $gym,
            branch: $branch,
            newValues: $membership->toArray(),
        );

        if ($initialPayment) {
            $this->auditLogService->log(
                event: 'payment.recorded',
                action: 'create',
                request: $request,
                subject: $initialPayment,
                gym: $gym,
                branch: $branch,
                newValues: $initialPayment->toArray(),
                context: ['source' => 'membership_assignment'],
            );
        }

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return $this->success(
            MemberMembershipResource::make($membership->load(['member', 'membershipPlan', 'payments.receipt', 'customFeeAuditLogs.changer'])),
            'Member membership created successfully.',
            201
        );
    }

    private function persistCustomFeeUpdate(UpdateCustomFeeRequest $request, MemberMembership $memberMembership)
    {
        if (! $request->user()?->can('updateCustomFee', $memberMembership)) {
            return $this->error('You do not have the required permission.', 403);
        }

        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);

        $oldValues = $memberMembership->only([
            'custom_fee_enabled',
            'custom_fee_amount',
            'discount_type',
            'discount_amount',
            'custom_joining_fee',
            'joining_fee_waived',
            'partial_month_fee',
            'pt_custom_fee',
            'final_payable_amount',
            'amount_paid',
            'due_amount',
            'due_date',
            'payment_status',
            'custom_fee_reason',
        ]);

        $memberMembership->fill($request->validated());
        $memberMembership->approved_by_admin_id = $request->user()->id;
        $memberMembership->due_date = $request->validated('due_date', $memberMembership->due_date);
        $this->membershipPricingService->recalculateMembership($memberMembership);
        $memberMembership->save();

        $newValues = $memberMembership->only(array_keys($oldValues));
        $reason = $memberMembership->custom_fee_reason ?? 'Custom fee updated.';
        $this->customFeeAuditService->log($memberMembership, $request->user(), $oldValues, $newValues, $reason);
        $this->auditLogService->log(
            event: 'membership.custom_fee.updated',
            action: 'update',
            request: $request,
            subject: $memberMembership,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $newValues,
            context: ['reason' => $reason],
        );

        $this->reminderService->syncMembershipReminders($memberMembership->fresh(['membershipPlan']));

        return $this->success(
            MemberMembershipResource::make($memberMembership->fresh(['member', 'membershipPlan', 'branch', 'payments.receipt', 'customFeeAuditLogs.changer'])),
            'Custom membership fee updated successfully.'
        );
    }

    private function resolveMemberCustomFeeMembership(Request $request, User $member): MemberMembership
    {
        $membershipId = $request->integer('member_membership_id');

        $membership = $this->memberCustomFeeMemberships($request, $member)
            ->when($membershipId > 0, fn (Builder $builder) => $builder->whereKey($membershipId))
            ->latest('id')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'member_membership_id' => ['The selected membership could not be found for this member in the current scope.'],
            ]);
        }

        return $membership;
    }

    private function memberCustomFeeMemberships(Request $request, User $member): Builder
    {
        $query = MemberMembership::query()
            ->where('member_id', $member->id)
            ->whereIn('gym_id', $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id'));

        if (! in_array($request->user()->active_role, [RoleName::GymOwner->value, RoleName::PlatformAdmin->value], true)) {
            $query->whereIn('branch_id', $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id'));
        }

        if ($request->filled('gym_id')) {
            $query->where('gym_id', $request->integer('gym_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return $query;
    }

    private function customFeeMembershipQuery(Request $request): Builder
    {
        $query = MemberMembership::query()
            ->where(function (Builder $builder): void {
                $builder->where('custom_fee_enabled', true)
                    ->orWhere('discount_amount', '>', 0)
                    ->orWhere('joining_fee_waived', true)
                    ->orWhere('partial_month_fee', '>', 0)
                    ->orWhere('pt_custom_fee', '>', 0)
                    ->orWhereColumn('custom_joining_fee', '!=', 'default_joining_fee');
            })
            ->whereIn('gym_id', $this->scopeResolver->gymsQuery($request->user())->pluck('gyms.id'));

        if (! in_array($request->user()->active_role, [RoleName::GymOwner->value, RoleName::PlatformAdmin->value], true)) {
            $query->whereIn('branch_id', $this->scopeResolver->branchesQuery($request->user())->pluck('branches.id'));
        }

        if ($request->filled('gym_id')) {
            $query->where('gym_id', $request->integer('gym_id'));
        }

        return $query;
    }

    private function updateLifecycle(
        Request $request,
        MemberMembership $memberMembership,
        string $event,
        string $message,
        callable $callback,
    ) {
        $this->authorize('update', $memberMembership);
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);

        $oldValues = $memberMembership->toArray();
        $membership = DB::transaction(function () use ($callback) {
            /** @var MemberMembership $updated */
            $updated = $callback();

            return $updated;
        });

        $this->auditLogService->log(
            event: $event,
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $membership->fresh()->toArray(),
            context: ['reason' => $request->validated('notes')],
        );

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return $this->success(
            MemberMembershipResource::make($membership->fresh(['member', 'membershipPlan'])),
            $message
        );
    }
}
