<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\MarkMembershipPaymentStatusRequest;
use App\Http\Requests\Billing\ReversePaymentRequest;
use App\Http\Requests\Web\Gym\ReverseGymLedgerEntryRequest;
use App\Http\Requests\Web\Gym\StoreGymLedgerEntryRequest;
use App\Http\Requests\Web\Gym\StorePaymentWebRequest;
use App\Models\ActivityLog;
use App\Models\GymLedgerEntry;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\AuditTimelineService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Billing\PaymentInvoicePdfService;
use App\Services\Billing\PaymentService;
use App\Services\Gym\GymLedgerService;
use App\Services\Web\CsvStreamService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly PaymentService $paymentService,
        private readonly PaymentInvoicePdfService $paymentInvoicePdfService,
        private readonly AuditLogService $auditLogService,
        private readonly AuditTimelineService $auditTimelineService,
        private readonly CsvStreamService $csvStreamService,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly GymLedgerService $gymLedgerService,
    ) {
    }

    public function index(Request $request): View|StreamedResponse
    {
        return $this->renderListing($request, 'all', 'Payments');
    }

    public function dues(Request $request): View|StreamedResponse
    {
        return $this->renderListing($request, 'dues', 'Pending Dues');
    }

    public function memberPayments(Request $request, User $member): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $member->load('memberProfile');
        abort_unless($member->memberProfile?->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertAnyPermission($request, [
            PermissionName::PaymentsView->value,
            PermissionName::MembershipsView->value,
        ], $gym, $member->memberProfile?->branch_id);
        $this->assertBillingViewAccess($request, $gym->id, $member->memberProfile?->branch_id);

        return $this->renderListing($request, 'member', 'Member Payment History', $member);
    }

    public function show(Request $request, Payment $payment): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($payment->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsView->value, $gym, $payment->branch_id);
        $this->assertBillingViewAccess($request, $gym->id, $payment->branch_id);

        return view('web.gym.payments.show', [
            'pageTitle' => 'Payment Detail',
            'breadcrumbs' => ['Gym', 'Payments', 'Payment Detail'],
            'payment' => $payment->load(['member.memberProfile.branch', 'membership.membershipPlan', 'branch', 'collector', 'receipt']),
        ]);
    }

    public function invoice(Request $request, Payment $payment)
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($payment->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsView->value, $gym, $payment->branch_id);
        $this->assertBillingViewAccess($request, $gym->id, $payment->branch_id);

        $invoice = $this->paymentInvoicePdfService->generate($payment);
        $relativePath = 'payment-invoices/'.$invoice['filename'];
        Storage::disk('local')->put($relativePath, $invoice['content']);

        if ($payment->receipt) {
            $payment->receipt->forceFill([
                'generated_at' => now(),
                'file_path' => $relativePath,
            ])->save();
        }

        return response($invoice['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice['filename'].'"',
            'Content-Length' => (string) strlen($invoice['content']),
        ]);
    }

    public function reports(Request $request): RedirectResponse
    {
        return redirect()->route('web.gym.reports.index', array_merge(
            $request->only(['gym', 'branch']),
            ['report' => 'revenue'],
        ));
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym);
        $this->assertCollectPaymentAccess($request, $gym->id);
        $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
        $selectedMemberId = $request->integer('member_id');

        return view('web.gym.payments.create', [
            'pageTitle' => 'Collect Payment',
            'breadcrumbs' => ['Gym', 'Payments', 'Collect Payment'],
            'gym' => $gym,
            'memberships' => MemberMembership::query()
                ->with(['member', 'membershipPlan', 'branch'])
                ->where('gym_id', $gym->id)
                ->whereIn('branch_id', $branchIds)
                ->when($selectedMemberId > 0, fn ($query) => $query->where('member_id', $selectedMemberId))
                ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])
                ->where('status', '!=', 'cancelled')
                ->orderBy('due_date')
                ->get(),
            'selectedMemberId' => $selectedMemberId > 0 ? $selectedMemberId : null,
        ]);
    }

    public function store(StorePaymentWebRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $membership = MemberMembership::query()->with(['membershipPlan', 'payments'])->findOrFail($request->validated('member_membership_id'));
        abort_unless($membership->gym_id === $gym->id, 404);

        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $membership->branch_id);
        $this->assertCollectPaymentAccess($request, $gym->id, $membership->branch_id);
        $payment = $this->paymentService->recordPayment($membership, $request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'web.gym.payment.recorded',
            action: 'create',
            request: $request,
            subject: $payment,
            gym: $gym,
            branch: $membership->branch,
            newValues: $payment->toArray(),
        );

        return redirect()->route('web.gym.payments.index')->with('status', 'Payment recorded successfully.');
    }

    public function markPaid(MarkMembershipPaymentStatusRequest $request, MemberMembership $memberMembership): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($memberMembership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $memberMembership->branch_id);
        $this->assertCollectPaymentAccess($request, $gym->id, $memberMembership->branch_id);

        $payment = $this->paymentService->markPaid(
            $memberMembership->fresh(['payments', 'membershipPlan']),
            $request->user(),
            $request->validated('payment_mode'),
            $request->validated('notes'),
            $request->validated('paid_at'),
        );

        $this->auditLogService->log(
            event: 'web.gym.payment.marked_paid',
            action: 'update',
            request: $request,
            subject: $payment,
            gym: $gym,
            branch: $memberMembership->branch,
            newValues: $payment->toArray(),
        );

        return back()->with('status', 'Membership marked paid successfully.');
    }

    public function markUnpaid(MarkMembershipPaymentStatusRequest $request, MemberMembership $memberMembership): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($memberMembership->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $memberMembership->branch_id);
        $this->assertCollectPaymentAccess($request, $gym->id, $memberMembership->branch_id);

        $membership = $this->paymentService->markUnpaid(
            $memberMembership->fresh(['payments', 'membershipPlan']),
            $request->validated('reason'),
        );

        $this->auditLogService->log(
            event: 'web.gym.payment.marked_unpaid',
            action: 'update',
            request: $request,
            subject: $membership,
            gym: $gym,
            branch: $memberMembership->branch,
            newValues: $membership->toArray(),
            context: ['reason' => $request->validated('reason')],
        );

        return back()->with('status', 'Membership marked unpaid successfully.');
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($payment->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $payment->branch_id);
        $this->assertCollectPaymentAccess($request, $gym->id, $payment->branch_id);

        $oldValues = $payment->toArray();
        $reversed = $this->paymentService->reversePayment(
            $payment->fresh(['membership.membershipPlan', 'receipt']),
            $request->validated('reason'),
        );

        $this->auditLogService->log(
            event: 'web.gym.payment.reversed',
            action: 'update',
            request: $request,
            subject: $reversed,
            gym: $gym,
            branch: $payment->branch,
            oldValues: $oldValues,
            newValues: $reversed->toArray(),
            context: ['reason' => $request->validated('reason')],
        );

        return back()->with('status', 'Payment reversed successfully.');
    }

    public function storeLedgerEntry(StoreGymLedgerEntryRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $request->integer('branch_id') ?: null);
        $this->assertCollectPaymentAccess($request, $gym->id, $request->integer('branch_id') ?: null);

        if ($request->filled('branch_id')) {
            $allowedBranchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
            abort_unless(in_array($request->integer('branch_id'), $allowedBranchIds, true), 403);
        }

        $entry = $this->gymLedgerService->createManualEntry($gym, $request->user(), $request->validated());

        $this->auditLogService->log(
            event: 'web.gym.ledger.entry.created',
            action: 'create',
            request: $request,
            subject: $entry,
            gym: $gym,
            branch: $entry->branch,
            newValues: $entry->toArray(),
        );

        return back()->with('status', 'Ledger entry recorded successfully.');
    }

    public function reverseLedgerEntry(ReverseGymLedgerEntryRequest $request, GymLedgerEntry $ledgerEntry): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        abort_unless($ledgerEntry->gym_id === $gym->id, 404);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsManage->value, $gym, $ledgerEntry->branch_id);
        $this->assertCollectPaymentAccess($request, $gym->id, $ledgerEntry->branch_id);

        $oldValues = $ledgerEntry->toArray();
        $reversed = $this->gymLedgerService->reverseManualEntry(
            $ledgerEntry,
            $request->user(),
            $request->validated('reason'),
        );

        $this->auditLogService->log(
            event: 'web.gym.ledger.entry.reversed',
            action: 'update',
            request: $request,
            subject: $reversed,
            gym: $gym,
            branch: $reversed->branch,
            oldValues: $oldValues,
            newValues: $reversed->toArray(),
            context: ['reason' => $request->validated('reason')],
        );

        return back()->with('status', 'Ledger entry reversed successfully.');
    }

    private function renderListing(Request $request, string $preset, string $pageTitle, ?User $member = null): View|StreamedResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::PaymentsView->value, $gym);
        $this->assertBillingViewAccess($request, $gym->id);

        $paymentsQuery = Payment::query()
            ->with(['member.memberProfile.branch', 'membership.membershipPlan', 'collector', 'receipt', 'branch'])
            ->where('gym_id', $gym->id);

        $membershipsQuery = MemberMembership::query()
            ->with(['member.memberProfile.branch', 'membershipPlan', 'branch'])
            ->where('gym_id', $gym->id);

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $paymentsQuery->where('branch_id', $branch->id);
            $membershipsQuery->where('branch_id', $branch->id);
        } else {
            $branchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
            $paymentsQuery->whereIn('branch_id', $branchIds);
            $membershipsQuery->whereIn('branch_id', $branchIds);
        }

        if ($member) {
            $paymentsQuery->where('member_id', $member->id);
            $membershipsQuery->where('member_id', $member->id);
        }

        if ($request->filled('member_search')) {
            $search = '%'.$request->string('member_search')->trim().'%';
            $paymentsQuery->whereHas('member', fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
            $membershipsQuery->whereHas('member', fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('phone', 'like', $search));
        }

        if ($request->filled('payment_mode')) {
            $paymentsQuery->where('payment_mode', $request->string('payment_mode'));
        }

        if ($request->filled('branch_id')) {
            $paymentsQuery->where('branch_id', $request->integer('branch_id'));
            $membershipsQuery->where('branch_id', $request->integer('branch_id'));
        }

        $statusFilter = $request->string('payment_status')->toString();
        if ($statusFilter !== '') {
            $paymentsQuery->whereHas('membership', fn ($builder) => $builder->where('payment_status', $statusFilter));
            $membershipsQuery->where('payment_status', $statusFilter);
        }

        if ($request->filled('start_date')) {
            $paymentsQuery->whereDate('paid_at', '>=', $request->date('start_date')?->toDateString());
        }

        if ($request->filled('end_date')) {
            $paymentsQuery->whereDate('paid_at', '<=', $request->date('end_date')?->toDateString());
        }

        $ledgerQuery = GymLedgerEntry::query()
            ->with(['branch:id,name', 'creator:id,name,email'])
            ->where('gym_id', $gym->id);

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $ledgerQuery->where(function (Builder $builder) use ($branch): void {
                $builder->where('branch_id', $branch->id)
                    ->orWhereNull('branch_id');
            });
        } else {
            $accessibleBranchIds = $this->gymWebPanelService->selectedBranchIds($request, $gym);
            $ledgerQuery->where(function (Builder $builder) use ($accessibleBranchIds): void {
                $builder->whereIn('branch_id', $accessibleBranchIds)
                    ->orWhereNull('branch_id');
            });
        }

        if ($request->filled('branch_id')) {
            $ledgerQuery->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('ledger_search')) {
            $search = '%'.$request->string('ledger_search')->trim().'%';
            $ledgerQuery->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('category', 'like', $search);
            });
        }

        if ($request->filled('ledger_direction')) {
            $ledgerQuery->where('direction', $request->string('ledger_direction'));
        }

        if ($request->filled('ledger_status')) {
            $ledgerQuery->where('status', $request->string('ledger_status'));
        }

        if ($request->filled('ledger_entry_type')) {
            $ledgerQuery->where('entry_type', $request->string('ledger_entry_type'));
        }

        if ($request->filled('start_date')) {
            $ledgerQuery->whereDate('occurred_at', '>=', $request->date('start_date')?->toDateString());
        }

        if ($request->filled('end_date')) {
            $ledgerQuery->whereDate('occurred_at', '<=', $request->date('end_date')?->toDateString());
        }

        if ($preset === 'dues') {
            $membershipsQuery->whereIn('payment_status', [
                PaymentStatus::Unpaid->value,
                PaymentStatus::Partial->value,
                PaymentStatus::Overdue->value,
            ]);
        }

        if ($preset === 'overdue') {
            $membershipsQuery->where('payment_status', PaymentStatus::Overdue->value);
        }

        if ($preset === 'paid') {
            $paymentsQuery->whereHas('membership', fn (Builder $builder) => $builder->where('payment_status', PaymentStatus::Paid->value));
        }

        if ($preset === 'partial') {
            $paymentsQuery->whereHas('membership', fn (Builder $builder) => $builder->where('payment_status', PaymentStatus::Partial->value));
        }

        if ($request->string('export')->toString() === 'csv') {
            return $this->csvStreamService->download(
                'gym-payments-'.$gym->id.'-'.now()->format('Ymd-His').'.csv',
                ['Member', 'Branch', 'Membership Plan', 'Amount', 'Payment Mode', 'Status', 'Paid At', 'Recorded By', 'Receipt'],
                $paymentsQuery->latest('paid_at')->get()->map(fn (Payment $payment) => [
                    $payment->member?->name ?? '',
                    $payment->branch?->name ?? '',
                    $payment->membership?->membershipPlan?->name ?? '',
                    number_format((float) $payment->amount, 2, '.', ''),
                    strtoupper((string) $payment->payment_mode),
                    (string) $payment->status,
                    optional($payment->paid_at)->format('Y-m-d H:i:s') ?? '',
                    $payment->collector?->name ?? '',
                    $payment->receipt_number ?? '',
                ]),
            );
        }

        if ($request->string('ledger_export')->toString() === 'csv') {
            return $this->csvStreamService->download(
                'gym-ledger-'.$gym->id.'-'.now()->format('Ymd-His').'.csv',
                ['Date', 'Type', 'Direction', 'Category', 'Title', 'Amount', 'Status', 'Branch', 'Reference', 'Recorded By'],
                $ledgerQuery->orderByDesc('occurred_at')->orderByDesc('id')->get()->map(fn (GymLedgerEntry $entry) => [
                    optional($entry->occurred_at)->format('Y-m-d H:i:s') ?? '',
                    (string) $entry->entry_type,
                    (string) $entry->direction,
                    (string) $entry->category,
                    (string) $entry->title,
                    number_format((float) $entry->amount, 2, '.', ''),
                    (string) $entry->status,
                    (string) ($entry->branch?->name ?? 'Gym-wide'),
                    (string) ($entry->reference ?? ''),
                    (string) ($entry->creator?->name ?? 'System'),
                ]),
            );
        }

        $payments = $paymentsQuery->latest('paid_at')->paginate(15)->withQueryString();
        $ledgerPaginator = (clone $ledgerQuery)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'ledger_page')
            ->withQueryString();
        $paymentIds = $payments->getCollection()->pluck('id')->all();
        $membershipIds = $payments->getCollection()->pluck('member_membership_id')->filter()->unique()->values()->all();
        $paymentSummaryQuery = clone $paymentsQuery;
        $duesSummaryQuery = clone $membershipsQuery;
        $windowPaymentsQuery = clone $paymentsQuery;
        $windowDuesQuery = clone $membershipsQuery;
        $ledgerSummaryQuery = clone $ledgerQuery;
        $ledgerWindowQuery = clone $ledgerQuery;

        $dailyStart = now()->startOfDay();
        $weeklyStart = now()->startOfWeek();
        $monthlyStart = now()->startOfMonth();

        $paymentActivityLogs = ActivityLog::query()
            ->with('actor')
            ->where('gym_id', $gym->id)
            ->where(function ($builder) use ($paymentIds, $membershipIds): void {
                $hasCondition = false;

                if ($paymentIds !== []) {
                    $hasCondition = true;
                    $builder->where(fn ($query) => $query
                        ->where('subject_type', Payment::class)
                        ->whereIn('subject_id', $paymentIds));
                }

                if ($membershipIds !== []) {
                    $hasCondition
                        ? $builder->orWhere(fn ($query) => $query
                            ->where('subject_type', MemberMembership::class)
                            ->whereIn('subject_id', $membershipIds)
                            ->where('event', 'like', '%payment%'))
                        : $builder->where(fn ($query) => $query
                            ->where('subject_type', MemberMembership::class)
                            ->whereIn('subject_id', $membershipIds)
                            ->where('event', 'like', '%payment%'));
                }

                if (! $hasCondition && $membershipIds === []) {
                    $builder->whereRaw('1 = 0');
                }
            })
            ->latest('occurred_at')
            ->take(20)
            ->get();

        $this->attachRunningBalances($ledgerPaginator, $ledgerSummaryQuery);
        $postedLedgerQuery = (clone $ledgerSummaryQuery)->where('status', 'posted');
        $ledgerInflow = (float) (clone $postedLedgerQuery)->where('direction', 'inflow')->sum('amount');
        $ledgerOutflow = (float) (clone $postedLedgerQuery)->where('direction', 'outflow')->sum('amount');

        return view('web.gym.payments.index', [
            'pageTitle' => $pageTitle,
            'breadcrumbs' => ['Gym', 'Payments'],
            'gym' => $gym,
            'member' => $member,
            'activeTab' => $preset,
            'payments' => $payments,
            'pendingDues' => (clone $membershipsQuery)
                ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])
                ->orderBy('due_date')
                ->take(20)
                ->get(),
            'overdueMemberships' => (clone $membershipsQuery)->where('payment_status', PaymentStatus::Overdue->value)->orderBy('due_date')->take(20)->get(),
            'monthlyCollection' => (float) (clone $paymentsQuery)
                ->where('status', PaymentRecordStatus::Recorded->value)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'branches' => $this->gymWebPanelService->accessibleBranches($request, $gym),
            'canCollectPayments' => $this->gymWebPanelService->canPermission($request, PermissionName::PaymentsManage->value, $gym),
            'paymentAuditTimeline' => $this->auditTimelineService->forActivityLogs($paymentActivityLogs),
            'ledgerEntries' => $ledgerPaginator,
            'summary' => [
                'recorded_payments' => (clone $paymentSummaryQuery)->count(),
                'collected_amount' => (float) (clone $paymentSummaryQuery)->sum('amount'),
                'avg_ticket' => (float) ((clone $paymentSummaryQuery)->count() > 0
                    ? (clone $paymentSummaryQuery)->sum('amount') / max(1, (clone $paymentSummaryQuery)->count())
                    : 0),
                'pending_due_amount' => (float) (clone $duesSummaryQuery)
                    ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])
                    ->sum('due_amount'),
                'overdue_due_amount' => (float) (clone $duesSummaryQuery)
                    ->where('payment_status', PaymentStatus::Overdue->value)
                    ->sum('due_amount'),
            ],
            'ledgerSummary' => [
                'inflow' => $ledgerInflow,
                'outflow' => $ledgerOutflow,
                'net' => $ledgerInflow - $ledgerOutflow,
                'closing_balance' => $ledgerInflow - $ledgerOutflow,
                'manual_entries' => (clone $ledgerSummaryQuery)->where('source_type', 'manual')->count(),
                'reversed_entries' => (clone $ledgerSummaryQuery)->where('status', 'reversed')->count(),
            ],
            'paymentModeBreakdown' => (clone $paymentSummaryQuery)
                ->selectRaw('payment_mode, COUNT(*) as payments_count, SUM(amount) as total_amount')
                ->groupBy('payment_mode')
                ->orderByDesc('total_amount')
                ->get(),
            'branchCollections' => (clone $paymentSummaryQuery)
                ->selectRaw('branch_id, COUNT(*) as payments_count, SUM(amount) as total_amount')
                ->with('branch:id,name')
                ->groupBy('branch_id')
                ->orderByDesc('total_amount')
                ->get(),
            'ledgerCategoryBreakdown' => (clone $postedLedgerQuery)
                ->selectRaw('category, direction, COUNT(*) as entries_count, SUM(amount) as total_amount')
                ->groupBy('category', 'direction')
                ->orderByDesc('total_amount')
                ->get(),
            'auditWindows' => [
                [
                    'label' => 'Daily Audit',
                    'range' => $dailyStart->format('d M Y').' - '.now()->format('d M Y'),
                    'payments_count' => (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $dailyStart)->count(),
                    'collected_amount' => (float) (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $dailyStart)->sum('amount'),
                    'spent_amount' => (float) (clone $ledgerWindowQuery)->where('status', 'posted')->where('direction', 'outflow')->where('occurred_at', '>=', $dailyStart)->sum('amount'),
                    'avg_ticket' => (float) ((clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $dailyStart)->avg('amount') ?? 0),
                    'open_due' => (float) (clone $windowDuesQuery)->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])->sum('due_amount'),
                    'overdue_due' => (float) (clone $windowDuesQuery)->where('payment_status', PaymentStatus::Overdue->value)->sum('due_amount'),
                ],
                [
                    'label' => 'Weekly Audit',
                    'range' => $weeklyStart->format('d M').' - '.now()->format('d M Y'),
                    'payments_count' => (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $weeklyStart)->count(),
                    'collected_amount' => (float) (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $weeklyStart)->sum('amount'),
                    'spent_amount' => (float) (clone $ledgerWindowQuery)->where('status', 'posted')->where('direction', 'outflow')->where('occurred_at', '>=', $weeklyStart)->sum('amount'),
                    'avg_ticket' => (float) ((clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $weeklyStart)->avg('amount') ?? 0),
                    'open_due' => (float) (clone $windowDuesQuery)->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])->sum('due_amount'),
                    'overdue_due' => (float) (clone $windowDuesQuery)->where('payment_status', PaymentStatus::Overdue->value)->sum('due_amount'),
                ],
                [
                    'label' => 'Monthly Audit',
                    'range' => $monthlyStart->format('d M').' - '.now()->format('d M Y'),
                    'payments_count' => (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $monthlyStart)->count(),
                    'collected_amount' => (float) (clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $monthlyStart)->sum('amount'),
                    'spent_amount' => (float) (clone $ledgerWindowQuery)->where('status', 'posted')->where('direction', 'outflow')->where('occurred_at', '>=', $monthlyStart)->sum('amount'),
                    'avg_ticket' => (float) ((clone $windowPaymentsQuery)->where('status', PaymentRecordStatus::Recorded->value)->where('paid_at', '>=', $monthlyStart)->avg('amount') ?? 0),
                    'open_due' => (float) (clone $windowDuesQuery)->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value, PaymentStatus::Overdue->value])->sum('due_amount'),
                    'overdue_due' => (float) (clone $windowDuesQuery)->where('payment_status', PaymentStatus::Overdue->value)->sum('due_amount'),
                ],
            ],
            'ledgerCategoryOptions' => [
                'rent' => 'Rent',
                'payroll' => 'Payroll',
                'utilities' => 'Utilities',
                'maintenance' => 'Maintenance',
                'marketing' => 'Marketing',
                'equipment' => 'Equipment',
                'supplies' => 'Supplies',
                'member_payment' => 'Member payment',
                'refund' => 'Refund',
                'adjustment' => 'Adjustment',
                'other' => 'Other',
            ],
        ]);
    }

    private function attachRunningBalances($paginator, Builder $baseQuery): void
    {
        $collection = $paginator->getCollection();

        if ($collection->isEmpty()) {
            return;
        }

        /** @var GymLedgerEntry $oldest */
        $oldest = $collection->last();

        $balanceBeforePage = (float) (clone $baseQuery)
            ->where('status', 'posted')
            ->where(function (Builder $builder) use ($oldest): void {
                $builder->where('occurred_at', '<', $oldest->occurred_at)
                    ->orWhere(function (Builder $nested) use ($oldest): void {
                        $nested->where('occurred_at', $oldest->occurred_at)
                            ->where('id', '<', $oldest->id);
                    });
            })
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'inflow' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance');

        $running = $balanceBeforePage;
        $mapped = [];

        $collection
            ->sortBy([
                ['occurred_at', 'asc'],
                ['id', 'asc'],
            ])
            ->each(function (GymLedgerEntry $entry) use (&$running, &$mapped): void {
                $impact = $this->gymLedgerService->signedAmount($entry);
                $running += $impact;
                $entry->setAttribute('impact_amount', $impact);
                $entry->setAttribute('running_balance', $running);
                $mapped[$entry->id] = $entry;
            });

        $paginator->setCollection($collection->map(fn (GymLedgerEntry $entry) => $mapped[$entry->id] ?? $entry));
    }

    private function assertBillingViewAccess(Request $request, int $gymId, ?int $branchId = null): void
    {
        $role = $request->user()?->active_role;

        if (in_array($role, ['gym_owner', 'platform_admin'], true)) {
            return;
        }

        abort_unless(
            $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'view_billing', $gymId, $branchId),
            403
        );
    }

    private function assertCollectPaymentAccess(Request $request, int $gymId, ?int $branchId = null): void
    {
        $role = $request->user()?->active_role;

        if (in_array($role, ['gym_owner', 'platform_admin'], true)) {
            return;
        }

        abort_unless(
            $this->scopedPermissionResolver->hasCustomPermission($request->user(), 'collect_payment', $gymId, $branchId),
            403
        );
    }
}
