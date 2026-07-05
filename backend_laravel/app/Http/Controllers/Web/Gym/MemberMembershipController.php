<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ExtendMembershipRequest;
use App\Http\Requests\Billing\RenewMembershipRequest;
use App\Http\Requests\Billing\StoreMemberMembershipRequest;
use App\Http\Requests\Billing\UpdateCustomFeeRequest;
use App\Http\Requests\Billing\UpdateMembershipLifecycleRequest;
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
use App\Services\Notification\ReminderService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MemberMembershipController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly BillingAccessService $billingAccessService,
        private readonly MembershipPricingService $membershipPricingService,
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly CustomFeeAuditService $customFeeAuditService,
        private readonly ReminderService $reminderService,
        private readonly AuditLogService $auditLogService,
        private readonly AuditTimelineService $auditTimelineService,
    ) {
    }

    public function index(Request $request): View
    {
        return $this->renderIndex($request, null, 'Memberships');
    }

    public function active(Request $request): View
    {
        return $this->renderIndex($request, 'active', 'Active Memberships');
    }

    public function expired(Request $request): View
    {
        return $this->renderIndex($request, 'expired', 'Expired Memberships');
    }

    public function expiringSoon(Request $request): View
    {
        return $this->renderIndex($request, 'expiring-soon', 'Expiring Soon Memberships');
    }

    public function show(Request $request, MemberMembership $membership): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($membership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsView->value,
            PermissionName::PaymentsView->value,
        ], $gym, $membership->branch_id);

        $paymentIds = Payment::query()
            ->where('member_membership_id', $membership->id)
            ->pluck('id')
            ->all();

        $activityLogs = ActivityLog::query()
            ->with('actor')
            ->where('gym_id', $membership->gym_id)
            ->where('branch_id', $membership->branch_id)
            ->where(function ($builder) use ($membership, $paymentIds): void {
                $builder->where(fn ($query) => $query
                    ->where('subject_type', MemberMembership::class)
                    ->where('subject_id', $membership->id));

                if ($paymentIds !== []) {
                    $builder->orWhere(fn ($query) => $query
                        ->where('subject_type', Payment::class)
                        ->whereIn('subject_id', $paymentIds));
                }
            })
            ->latest('occurred_at')
            ->take(20)
            ->get();

        $membership->load([
            'member.memberProfile.branch',
            'membershipPlan',
            'branch',
            'payments.receipt',
            'payments.collector',
            'customFeeAuditLogs.changer',
            'approver',
        ]);

        return view('web.gym.memberships.show', [
            'pageTitle' => 'Membership Detail',
            'breadcrumbs' => ['Gym', 'Memberships', 'Membership Detail'],
            'gym' => $gym,
            'membership' => $membership,
            'activityTimeline' => $this->auditTimelineService->forActivityLogs($activityLogs),
            'customFeeTimeline' => $this->auditTimelineService->forCustomFeeAudits($membership->customFeeAuditLogs),
            'canManageMemberships' => $this->gymWebPanelService->canPermission($request, PermissionName::MembershipsManage->value, $gym, $membership->branch_id),
            'canCollectPayments' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym, $membership->branch_id),
            'canEditCustomFee' => $this->gymWebPanelService->canPermission($request, PermissionName::EditCustomFee->value, $gym, $membership->branch_id),
        ]);
    }

    public function customFeesIndex(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsView->value,
            PermissionName::PaymentsView->value,
            PermissionName::EditCustomFee->value,
        ], $gym);

        $query = $this->customFeeMembershipQuery($request, $gym)
            ->with([
                'member.memberProfile.branch',
                'membershipPlan',
                'branch',
                'latestCustomFeeAuditLog.changer',
            ]);

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

        if ($request->boolean('due_only')) {
            $query->where('due_amount', '>', 0);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('approval_state')) {
            match ($request->string('approval_state')->toString()) {
                'pending' => $query->whereNull('approved_by_admin_id'),
                'approved' => $query->whereNotNull('approved_by_admin_id'),
                default => null,
            };
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        $customFeeSummaryQuery = clone $query;
        $memberships = $query->latest('id')->paginate(15)->withQueryString();

        return view('web.gym.custom-fees.index', [
            'pageTitle' => 'Custom Fees',
            'breadcrumbs' => ['Gym', 'Billing', 'Custom Fees'],
            'gym' => $gym,
            'memberships' => $memberships,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'plans' => MembershipPlan::query()
                ->where('gym_id', $gym->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'canEditCustomFee' => $this->gymWebPanelService->canPermission($request, PermissionName::EditCustomFee->value, $gym),
            'summary' => [
                'memberships' => (clone $customFeeSummaryQuery)->count(),
                'discounted_memberships' => (clone $customFeeSummaryQuery)->where(function (Builder $builder): void {
                    $builder->where('discount_amount', '>', 0)->orWhere('joining_fee_waived', true);
                })->count(),
                'due_amount' => (float) (clone $customFeeSummaryQuery)->sum('due_amount'),
                'pending_approvals' => (clone $customFeeSummaryQuery)->whereNull('approved_by_admin_id')->count(),
                'waived_joining_fee' => (clone $customFeeSummaryQuery)->where('joining_fee_waived', true)->count(),
                'pt_overrides' => (clone $customFeeSummaryQuery)->where('pt_custom_fee', '>', 0)->count(),
            ],
        ]);
    }

    public function customFeeAuditLogs(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsView->value,
            PermissionName::PaymentsView->value,
            PermissionName::EditCustomFee->value,
        ], $gym);

        $auditLogs = CustomFeeAuditLog::query()
            ->with(['member.memberProfile.branch', 'membership.membershipPlan', 'membership.branch', 'changer'])
            ->where('gym_id', $gym->id);

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $auditLogs->whereHas('membership', fn (Builder $builder) => $builder->where('branch_id', $branch->id));
        } elseif ($request->user()?->active_role !== 'gym_owner') {
            $branchIds = $this->gymWebPanelService->accessibleBranchIds($request, $gym);
            $auditLogs->whereHas('membership', fn (Builder $builder) => $builder->whereIn('branch_id', $branchIds));
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $auditLogs->whereHas('member', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        if ($request->filled('changed_by')) {
            $auditLogs->where('changed_by', $request->integer('changed_by'));
        }

        $logs = $auditLogs->latest('changed_at')->paginate(15)->withQueryString();

        return view('web.gym.custom-fees.audit-logs', [
            'pageTitle' => 'Custom Fee Audit Logs',
            'breadcrumbs' => ['Gym', 'Billing', 'Custom Fee Audit Logs'],
            'gym' => $gym,
            'logs' => $logs,
            'actors' => User::query()
                ->whereIn('id', CustomFeeAuditLog::query()
                    ->where('gym_id', $gym->id)
                    ->distinct()
                    ->pluck('changed_by')
                    ->filter()
                    ->all())
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function assignForm(Request $request, User $member): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $member->load('memberProfile');
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipsView->value, $gym, $member->memberProfile?->branch_id);
        $latestMembership = $member->memberMemberships()
            ->where('gym_id', $gym->id)
            ->currentFirst()
            ->first();

        return view('web.gym.memberships.assign', [
            'pageTitle' => $latestMembership ? 'Change Membership Plan' : 'Assign Membership',
            'breadcrumbs' => ['Gym', 'Members', $member->name, $latestMembership ? 'Change Membership Plan' : 'Assign Membership'],
            'member' => $member,
            'plans' => MembershipPlan::query()
                ->where('gym_id', $gym->id)
                ->where('status', 'active')
                ->when($member->memberProfile?->branch_id, fn ($query, $branchId) => $query->where(function ($builder) use ($branchId): void {
                    $builder->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }))
                ->orderBy('name')
                ->get(),
            'latestMembership' => $latestMembership,
        ]);
    }

    public function assign(StoreMemberMembershipRequest $request, User $member): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $member->load('memberProfile');
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);

        $branchId = (int) ($member->memberProfile?->branch_id ?? $request->validated('branch_id'));
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsManage->value,
            PermissionName::MembersManage->value,
        ], $gym, $branchId);

        $validated = $request->safe()->merge([
            'gym_id' => $gym->id,
            'branch_id' => $branchId,
            'member_id' => $member->id,
        ])->all();

        $membership = DB::transaction(function () use ($request, $validated) {
            $gym = $this->billingAccessService->assertGymAccess($request->user(), $validated['gym_id']);
            $branch = $this->billingAccessService->assertBranchAccess($request->user(), $validated['gym_id'], $validated['branch_id']);
            $plan = MembershipPlan::query()->findOrFail($validated['membership_plan_id']);
            $this->billingAccessService->assertPlanBelongsToScope($plan, $validated['gym_id'], $validated['branch_id']);

            ['membership' => $membership, 'initial_payment' => $initialPayment] = $this->membershipEnrollmentService->enroll(
                $plan,
                $request->user(),
                $validated,
            );

            $this->membershipLifecycleService->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

            if ($membership->custom_fee_enabled) {
                $this->customFeeAuditService->log(
                    $membership,
                    $request->user(),
                    [],
                    $membership->only([
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
                    ]),
                    $membership->custom_fee_reason ?? 'Initial custom fee applied.',
                );
            }

            $this->auditLogService->log(
                event: 'web.gym.membership.created',
                action: 'create',
                request: $request,
                subject: $membership,
                gym: $gym,
                branch: $branch,
                newValues: $membership->toArray(),
            );

            if ($initialPayment) {
                $this->auditLogService->log(
                    event: 'web.gym.payment.recorded',
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

            return $membership;
        });

        return redirect()
            ->route('web.gym.members.custom-fee', $member)
            ->with('status', 'Membership assigned successfully.');
    }

    public function renew(RenewMembershipRequest $request, MemberMembership $membership): RedirectResponse
    {
        $gym = $this->resolveMembershipManageScope($request, $membership);
        $oldValues = $membership->toArray();

        $renewed = DB::transaction(function () use ($request, $membership) {
            $result = $this->membershipLifecycleService->renew($membership, $request->user(), [
                'start_date' => $request->validated('start_date'),
                'due_date' => $request->validated('due_date'),
                'status' => $request->validated('status', MembershipStatus::Active->value),
                'amount_paid' => $request->validated('amount_paid', 0),
                'initial_payment_mode' => $request->validated('initial_payment_mode'),
                'paid_at' => $request->validated('paid_at'),
                'external_reference' => $request->validated('external_reference'),
                'payment_notes' => $request->validated('payment_notes'),
                'allow_overpayment' => $request->boolean('allow_overpayment'),
            ]);

            $renewed = $result['membership'];
            $initialPayment = $result['initial_payment'];

            $this->auditLogService->log(
                event: 'web.gym.membership.renewed',
                action: 'create',
                request: $request,
                subject: $renewed,
                gym: $renewed->gym,
                branch: $renewed->branch,
                oldValues: ['renewed_from_membership_id' => $membership->id],
                newValues: $renewed->toArray(),
                context: ['reason' => $request->validated('notes')],
            );

            if ($initialPayment) {
                $this->auditLogService->log(
                    event: 'web.gym.payment.recorded',
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

        return back()->with('status', 'Membership renewed successfully.');
    }

    public function freeze(UpdateMembershipLifecycleRequest $request, MemberMembership $membership): RedirectResponse
    {
        $this->resolveMembershipManageScope($request, $membership);
        $oldValues = $membership->toArray();
        $membership = $this->membershipLifecycleService->freeze($membership);

        $this->auditLogService->log(
            event: 'web.gym.membership.frozen',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $membership->gym,
            branch: $membership->branch,
            oldValues: $oldValues,
            newValues: $membership->fresh()->toArray(),
            context: ['reason' => $request->validated('notes')],
        );

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return back()->with('status', 'Membership frozen successfully.');
    }

    public function extend(ExtendMembershipRequest $request, MemberMembership $membership): RedirectResponse
    {
        $this->resolveMembershipManageScope($request, $membership);
        $oldValues = $membership->toArray();
        $membership = $this->membershipLifecycleService->extend(
            $membership,
            $request->validated('extra_days'),
            $request->validated('due_date')
        );

        $this->auditLogService->log(
            event: 'web.gym.membership.extended',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $membership->gym,
            branch: $membership->branch,
            oldValues: $oldValues,
            newValues: $membership->fresh()->toArray(),
            context: ['reason' => $request->validated('notes')],
        );

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return back()->with('status', 'Membership extended successfully.');
    }

    public function reactivate(UpdateMembershipLifecycleRequest $request, MemberMembership $membership): RedirectResponse
    {
        $this->resolveMembershipManageScope($request, $membership);
        $oldValues = $membership->toArray();
        $membership = $this->membershipLifecycleService->reactivate(
            $membership,
            $request->validated('due_date')
        );

        $this->auditLogService->log(
            event: 'web.gym.membership.reactivated',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $membership->gym,
            branch: $membership->branch,
            oldValues: $oldValues,
            newValues: $membership->fresh()->toArray(),
            context: ['reason' => $request->validated('notes')],
        );

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return back()->with('status', 'Membership reactivated successfully.');
    }

    public function cancel(UpdateMembershipLifecycleRequest $request, MemberMembership $membership): RedirectResponse
    {
        $this->resolveMembershipManageScope($request, $membership);
        $oldValues = $membership->toArray();
        $membership = $this->membershipLifecycleService->cancel($membership);

        $this->auditLogService->log(
            event: 'web.gym.membership.cancelled',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $membership->gym,
            branch: $membership->branch,
            oldValues: $oldValues,
            newValues: $membership->fresh()->toArray(),
            context: ['reason' => $request->validated('notes')],
        );

        $this->reminderService->syncMembershipReminders($membership->fresh());

        return back()->with('status', 'Membership cancelled successfully.');
    }

    public function customFeeForm(Request $request, User $member): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $member->load('memberProfile');
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsView->value,
            PermissionName::PaymentsView->value,
            PermissionName::EditCustomFee->value,
        ], $gym, $member->memberProfile?->branch_id);

        $canEditCustomFee = true;

        try {
            $this->gymWebPanelService->assertPermission(
                $request,
                PermissionName::EditCustomFee->value,
                $gym,
                $member->memberProfile?->branch_id,
            );
        } catch (HttpException) {
            $canEditCustomFee = false;
        }

        return view('web.gym.memberships.custom-fee', [
            'pageTitle' => 'Custom Member Fee',
            'breadcrumbs' => ['Gym', 'Members', $member->name, 'Custom Fee'],
            'member' => $member,
            'canEditCustomFee' => $canEditCustomFee,
            'memberships' => $member->memberMemberships()
                ->with(['membershipPlan', 'branch', 'customFeeAuditLogs.changer', 'payments'])
                ->where('gym_id', $gym->id)
                ->when(
                    $request->user()?->active_role !== 'gym_owner',
                    fn (Builder $query) => $query->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym))
                )
                ->currentFirst()
                ->get()
                ->each(function (MemberMembership $membership): void {
                    $membership->setAttribute(
                        'custom_fee_timeline',
                        $this->auditTimelineService->forCustomFeeAudits($membership->customFeeAuditLogs)
                    );
                }),
            'selectedMembershipId' => (int) ($request->integer('member_membership_id') ?: 0),
        ]);
    }

    public function updateMemberCustomFee(UpdateCustomFeeRequest $request, User $member): RedirectResponse
    {
        $memberMembership = $this->resolveMemberCustomFeeMembership($request, $member);

        return $this->persistCustomFeeUpdate($request, $memberMembership, redirectToMemberRoute: true);
    }

    public function updateCustomFee(UpdateCustomFeeRequest $request, MemberMembership $memberMembership): RedirectResponse
    {
        return $this->persistCustomFeeUpdate($request, $memberMembership);
    }

    private function renderIndex(Request $request, ?string $preset, string $pageTitle): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::MembershipsView->value,
            PermissionName::PaymentsView->value,
        ], $gym);

        $query = MemberMembership::query()
            ->with(['member.memberProfile.branch', 'membershipPlan.branch'])
            ->where('gym_id', $gym->id);

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $query->where('branch_id', $branch->id);
        } elseif ($request->user()?->active_role !== 'gym_owner') {
            $query->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym));
        }

        $statusFilter = $preset ?? ($request->filled('status') ? $request->string('status')->toString() : null);

        if ($statusFilter === 'expiring-soon') {
            $query->where('status', MembershipStatus::Active->value)
                ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
        } elseif ($statusFilter === 'expired') {
            $query->where(function (Builder $builder): void {
                $builder->where('status', MembershipStatus::Expired->value)
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('status', MembershipStatus::Active->value)
                            ->whereDate('expiry_date', '<', now()->toDateString());
                    });
            });
        } elseif (in_array($statusFilter, [MembershipStatus::Active->value, MembershipStatus::Frozen->value, MembershipStatus::Cancelled->value], true)) {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('plan_id')) {
            $query->where('membership_plan_id', $request->integer('plan_id'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }

        if ($request->filled('billing_period')) {
            $billingPeriod = $request->string('billing_period')->toString();
            $query->whereHas('membershipPlan', fn (Builder $builder) => $builder->where('billing_period', $billingPeriod));
        }

        if ($request->boolean('custom_fee_only')) {
            $query->where('custom_fee_enabled', true);
        }

        if ($request->boolean('due_only')) {
            $query->where('due_amount', '>', 0);
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        $membershipSummaryQuery = clone $query;
        $memberships = $query->latest('id')->paginate(15)->withQueryString();
        $plans = MembershipPlan::query()
            ->where('gym_id', $gym->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('web.gym.memberships.index', [
            'pageTitle' => $pageTitle,
            'breadcrumbs' => ['Gym', 'Memberships'],
            'gym' => $gym,
            'memberships' => $memberships,
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'plans' => $plans,
            'activeFilter' => $statusFilter,
            'canManageMemberships' => $this->gymWebPanelService->canPermission($request, PermissionName::MembershipsManage->value, $gym),
            'canCollectPayments' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym),
            'summary' => [
                'memberships' => (clone $membershipSummaryQuery)->count(),
                'due_amount' => (float) (clone $membershipSummaryQuery)->sum('due_amount'),
                'overdue' => (clone $membershipSummaryQuery)->where('payment_status', PaymentStatus::Overdue->value)->count(),
                'frozen' => (clone $membershipSummaryQuery)->where('status', MembershipStatus::Frozen->value)->count(),
                'expiring_soon' => (clone $membershipSummaryQuery)
                    ->where('status', MembershipStatus::Active->value)
                    ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                    ->count(),
                'custom_fee' => (clone $membershipSummaryQuery)->where('custom_fee_enabled', true)->count(),
            ],
            'billingPeriods' => $plans->pluck('billing_period')->filter()->unique()->values(),
        ]);
    }

    private function resolveMembershipManageScope(Request $request, MemberMembership $membership)
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($membership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::MembershipsManage->value, $gym, $membership->branch_id);

        return $gym;
    }

    private function persistCustomFeeUpdate(
        UpdateCustomFeeRequest $request,
        MemberMembership $memberMembership,
        bool $redirectToMemberRoute = false
    ): RedirectResponse {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($memberMembership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::EditCustomFee->value, $gym, $memberMembership->branch_id);

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
            event: 'web.gym.membership.custom_fee.updated',
            action: 'update',
            request: $request,
            subject: $memberMembership,
            gym: $memberMembership->gym,
            branch: $memberMembership->branch,
            oldValues: $oldValues,
            newValues: $newValues,
            context: ['reason' => $reason],
        );

        $this->reminderService->syncMembershipReminders($memberMembership->fresh(['membershipPlan']));

        if ($redirectToMemberRoute) {
            return redirect()
                ->route('web.gym.members.custom-fee', [
                    'member' => $memberMembership->member_id,
                    'member_membership_id' => $memberMembership->id,
                ])
                ->with('status', 'Custom fee updated successfully.');
        }

        return back()->with('status', 'Custom fee updated successfully.');
    }

    private function resolveMemberCustomFeeMembership(Request $request, User $member): MemberMembership
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $member->load('memberProfile');
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);

        $membershipId = $request->integer('member_membership_id');

        $query = $member->memberMemberships()
            ->where('gym_id', $gym->id)
            ->when(
                $request->user()?->active_role !== 'gym_owner',
                fn (Builder $builder) => $builder->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym))
            );

        $membership = $membershipId > 0
            ? $query->whereKey($membershipId)->first()
            : $query->latest('id')->first();

        abort_unless($membership !== null, 404);

        return $membership;
    }

    private function customFeeMembershipQuery(Request $request, $gym): Builder
    {
        $query = MemberMembership::query()
            ->where('gym_id', $gym->id)
            ->where(function (Builder $builder): void {
                $builder->where('custom_fee_enabled', true)
                    ->orWhere('discount_amount', '>', 0)
                    ->orWhere('joining_fee_waived', true)
                    ->orWhere('partial_month_fee', '>', 0)
                    ->orWhere('pt_custom_fee', '>', 0)
                    ->orWhereColumn('custom_joining_fee', '!=', 'default_joining_fee');
            });

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $query->where('branch_id', $branch->id);
        } elseif ($request->user()?->active_role !== 'gym_owner') {
            $query->whereIn('branch_id', $this->gymWebPanelService->accessibleBranchIds($request, $gym));
        }

        return $query;
    }
}
