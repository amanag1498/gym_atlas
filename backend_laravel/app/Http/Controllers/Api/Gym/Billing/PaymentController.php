<?php

namespace App\Http\Controllers\Api\Gym\Billing;

use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\MarkMembershipPaymentStatusRequest;
use App\Http\Requests\Billing\StoreGymPaymentRequest;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Resources\Billing\MemberMembershipResource;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Billing\BillingAccessService;
use App\Services\Billing\PaymentService;
use App\Services\Authorization\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly BillingAccessService $billingAccessService,
        private readonly PaymentService $paymentService,
        private readonly AuditLogService $auditLogService,
        private readonly ScopeResolver $scopeResolver,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertBillingViewAccess($request, $request->integer('gym_id'), $request->integer('branch_id'));
        $payments = $this->basePaymentsQuery($request)
            ->latest('paid_at')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($payments, PaymentResource::collection($payments->getCollection()), 'Payments fetched successfully.');
    }

    public function dues(Request $request)
    {
        $this->assertBillingViewAccess($request, $request->integer('gym_id'), $request->integer('branch_id'));
        $query = $this->baseMembershipsQuery($request)
            ->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Partial->value,
                PaymentStatus::Overdue->value,
            ])
            ->orderBy('due_date');

        if ($request->boolean('overdue_only')) {
            $query->where('payment_status', PaymentStatus::Overdue->value);
        }

        $memberships = $query->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($memberships, MemberMembershipResource::collection($memberships->getCollection()), 'Pending dues fetched successfully.');
    }

    public function memberPayments(User $member, Request $request)
    {
        $this->assertBillingViewAccess($request, $request->integer('gym_id'), $request->integer('branch_id'));
        $payments = $this->basePaymentsQuery($request)
            ->where('member_id', $member->id)
            ->latest('paid_at')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($payments, PaymentResource::collection($payments->getCollection()), 'Member payment history fetched successfully.');
    }

    public function reports(Request $request)
    {
        $this->assertBillingViewAccess($request, $request->integer('gym_id'), $request->integer('branch_id'));
        $paymentsQuery = $this->basePaymentsQuery($request);
        $duesQuery = $this->baseMembershipsQuery($request);

        $totalCollection = (float) (clone $paymentsQuery)
            ->where('status', PaymentRecordStatus::Recorded->value)
            ->sum('amount');

        $monthlyCollection = (float) (clone $paymentsQuery)
            ->where('status', PaymentRecordStatus::Recorded->value)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $pendingDues = (float) (clone $duesQuery)
            ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])
            ->sum('due_amount');

        $overdueDues = (float) (clone $duesQuery)
            ->where('payment_status', PaymentStatus::Overdue->value)
            ->sum('due_amount');

        return $this->success([
            'total_collection' => $totalCollection,
            'monthly_collection' => $monthlyCollection,
            'pending_dues' => $pendingDues,
            'overdue_dues' => $overdueDues,
            'payment_mode_summary' => (clone $paymentsQuery)
                ->selectRaw('payment_mode, SUM(amount) as total_amount, COUNT(*) as payments_count')
                ->groupBy('payment_mode')
                ->get()
                ->map(fn (Payment $payment): array => [
                    'payment_mode' => $payment->payment_mode,
                    'total_amount' => (float) $payment->total_amount,
                    'payments_count' => (int) $payment->payments_count,
                ])
                ->values(),
        ], 'Payment report fetched successfully.');
    }

    public function show(Payment $payment, Request $request)
    {
        $this->billingAccessService->assertGymAccess($request->user(), $payment->gym_id);
        $this->billingAccessService->assertBranchAccess($request->user(), $payment->gym_id, $payment->branch_id);
        $this->assertBillingViewAccess($request, $payment->gym_id, $payment->branch_id);

        return $this->success(
            PaymentResource::make($payment->load(['member', 'membership.membershipPlan', 'branch', 'collector', 'receipt'])),
            'Payment fetched successfully.'
        );
    }

    public function history(MemberMembership $memberMembership, Request $request)
    {
        $this->authorize('view', $memberMembership);
        $this->billingAccessService->assertMembershipAccess($request->user(), $memberMembership);
        $this->assertBillingViewAccess($request, $memberMembership->gym_id, $memberMembership->branch_id);

        $payments = $memberMembership->payments()
            ->with('receipt')
            ->latest('paid_at')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($payments, PaymentResource::collection($payments->getCollection()), 'Payment history fetched successfully.');
    }

    public function storeForMembership(StorePaymentRequest $request, MemberMembership $memberMembership)
    {
        $this->authorize('update', $memberMembership);
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);
        $this->assertCollectPaymentAccess($request, $memberMembership->gym_id, $memberMembership->branch_id);

        $payment = $this->paymentService->recordPayment($memberMembership->fresh(['payments', 'membershipPlan']), $request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'payment.recorded',
            action: 'create',
            request: $request,
            subject: $payment,
            gym: $gym,
            branch: $branch,
            newValues: $payment->toArray(),
        );

        return $this->success(PaymentResource::make($payment), 'Payment recorded successfully.', 201);
    }

    public function store(StoreGymPaymentRequest $request)
    {
        $memberMembership = MemberMembership::query()->findOrFail($request->validated('member_membership_id'));

        return $this->storeForMembership($request, $memberMembership);
    }

    public function markPaid(MarkMembershipPaymentStatusRequest $request, MemberMembership $memberMembership)
    {
        $this->authorize('update', $memberMembership);
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);
        $this->assertCollectPaymentAccess($request, $memberMembership->gym_id, $memberMembership->branch_id);

        $payment = $this->paymentService->markPaid(
            $memberMembership->fresh(['payments', 'membershipPlan']),
            $request->user(),
            $request->validated('payment_mode'),
            $request->validated('notes'),
            $request->validated('paid_at'),
        );

        $this->auditLogService->log(
            event: 'payment.marked_paid',
            action: 'update',
            request: $request,
            subject: $payment,
            gym: $gym,
            branch: $branch,
            newValues: $payment->toArray(),
        );

        return $this->success(PaymentResource::make($payment), 'Membership marked paid successfully.');
    }

    public function markUnpaid(MarkMembershipPaymentStatusRequest $request, MemberMembership $memberMembership)
    {
        $this->authorize('update', $memberMembership);
        $gym = $this->billingAccessService->assertGymAccess($request->user(), $memberMembership->gym_id);
        $branch = $this->billingAccessService->assertBranchAccess($request->user(), $memberMembership->gym_id, $memberMembership->branch_id);
        $this->assertCollectPaymentAccess($request, $memberMembership->gym_id, $memberMembership->branch_id);

        $membership = $this->paymentService->markUnpaid(
            $memberMembership->fresh(['payments', 'membershipPlan']),
            $request->validated('reason'),
        );

        $this->auditLogService->log(
            event: 'payment.marked_unpaid',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $gym,
            branch: $branch,
            newValues: $membership->toArray(),
            context: ['reason' => $request->validated('reason')],
        );

        return $this->success(
            MemberMembershipResource::make($membership->load(['member', 'membershipPlan', 'payments.receipt'])),
            'Membership marked unpaid successfully.'
        );
    }

    private function basePaymentsQuery(Request $request): Builder
    {
        $query = Payment::query()
            ->with(['member', 'membership.membershipPlan', 'branch', 'collector', 'receipt'])
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

        if ($request->filled('payment_mode')) {
            $query->where('payment_mode', $request->string('payment_mode')->toString());
        }

        if ($request->filled('payment_status')) {
            $query->whereHas('membership', fn (Builder $builder) => $builder->where('payment_status', $request->string('payment_status')->toString()));
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', fn (Builder $builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('paid_at', '>=', $request->date('start_date')?->toDateString());
        }

        if ($request->filled('end_date')) {
            $query->whereDate('paid_at', '<=', $request->date('end_date')?->toDateString());
        }

        return $query;
    }

    private function baseMembershipsQuery(Request $request): Builder
    {
        $query = MemberMembership::query()
            ->with(['member', 'membershipPlan', 'branch'])
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

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $query->whereHas('member', fn (Builder $builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status')->toString());
        }

        return $query;
    }

    private function assertBillingViewAccess(Request $request, ?int $gymId = null, ?int $branchId = null): void
    {
        $role = $request->user()?->active_role;

        if (in_array($role, [RoleName::GymOwner->value, RoleName::PlatformAdmin->value], true)) {
            return;
        }

        abort_unless(
            $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'view_billing', $gymId, $branchId),
            403
        );
    }

    private function assertCollectPaymentAccess(Request $request, ?int $gymId = null, ?int $branchId = null): void
    {
        $role = $request->user()?->active_role;

        if (in_array($role, [RoleName::GymOwner->value, RoleName::PlatformAdmin->value], true)) {
            return;
        }

        abort_unless(
            $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'collect_payment', $gymId, $branchId),
            403
        );
    }
}
