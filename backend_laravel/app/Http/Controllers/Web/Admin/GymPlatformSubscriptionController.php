<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\StoreGymPlatformSubscriptionRequest;
use App\Http\Requests\Web\Platform\UpdateGymPlatformSubscriptionRequest;
use App\Models\Gym;
use App\Models\GymPlatformSubscription;
use App\Models\GymPlatformSubscriptionInvoice;
use App\Models\PlatformSubscriptionPlan;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\PlatformSubscriptionLedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GymPlatformSubscriptionController extends Controller
{
    private const BILLING_STATUSES = ['trialing', 'active', 'past_due'];

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PlatformSubscriptionLedgerService $platformSubscriptionLedgerService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->platformSubscriptionLedgerService->syncInvoiceStatuses();

        $query = GymPlatformSubscription::query()
            ->with(['gym.owner', 'plan', 'assignedBy', 'latestInvoice'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($builder) => $builder
                ->whereHas('gym', fn ($gymQuery) => $gymQuery
                    ->where('name', 'like', $search)
                    ->orWhere('slug', 'like', $search)
                    ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)))
                ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('name', 'like', $search)));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('gym_id')) {
            $query->where('gym_id', $request->integer('gym_id'));
        }

        $today = now()->startOfDay();
        $endOfMonth = now()->endOfMonth();
        $nextThirtyDays = now()->addDays(30)->endOfDay();

        $monthlyDueQuery = GymPlatformSubscriptionInvoice::query()->whereBetween('due_at', [$today, $endOfMonth]);
        $overdueQuery = GymPlatformSubscriptionInvoice::query()
            ->whereIn('status', ['due', 'overdue'])
            ->whereDate('due_at', '<', $today);
        $upcomingQuery = GymPlatformSubscriptionInvoice::query()
            ->whereIn('status', ['due', 'overdue'])
            ->whereBetween('due_at', [$today, $nextThirtyDays]);
        $realizedQuery = GymPlatformSubscriptionInvoice::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $endOfMonth]);
        $mrrQuery = GymPlatformSubscription::query()->whereIn('status', ['active', 'trialing']);

        $dueSubscriptions = GymPlatformSubscriptionInvoice::query()
            ->with(['subscription.gym.owner', 'subscription.plan', 'generatedBy', 'paidBy'])
            ->whereIn('status', ['due', 'overdue'])
            ->whereDate('due_at', '<=', $endOfMonth)
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $invoiceLedger = GymPlatformSubscriptionInvoice::query()
            ->with(['subscription.gym.owner', 'plan', 'generatedBy', 'paidBy'])
            ->latest('issued_at')
            ->paginate(12, ['*'], 'ledger_page')
            ->withQueryString();

        return view('web.admin.gym-platform-subscriptions.index', [
            'pageTitle' => 'Gym Billing',
            'breadcrumbs' => ['Platform', 'Gym Billing'],
            'subscriptions' => $query->paginate(15)->withQueryString(),
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'totalSubscriptionsCount' => GymPlatformSubscription::query()->count(),
            'activeSubscriptionsCount' => GymPlatformSubscription::query()->where('status', 'active')->count(),
            'trialingSubscriptionsCount' => GymPlatformSubscription::query()->where('status', 'trialing')->count(),
            'pastDueSubscriptionsCount' => GymPlatformSubscription::query()->where('status', 'past_due')->count(),
            'monthlyRevenueDue' => (float) $monthlyDueQuery->sum('total_amount'),
            'overdueRevenueExposure' => (float) $overdueQuery->sum('total_amount'),
            'upcomingThirtyDayRevenue' => (float) $upcomingQuery->sum('total_amount'),
            'realizedRevenue' => (float) $realizedQuery->sum('total_amount'),
            'runRateRevenue' => (float) $mrrQuery->sum('billing_amount'),
            'monthlyDueCount' => (clone $monthlyDueQuery)->count(),
            'overdueCount' => (clone $overdueQuery)->count(),
            'paidInvoiceCount' => (clone $realizedQuery)->count(),
            'dueSubscriptions' => $dueSubscriptions,
            'invoiceLedger' => $invoiceLedger,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedGym = $request->filled('gym') ? Gym::query()->find($request->integer('gym')) : null;
        $selectedPlan = $request->filled('plan') ? PlatformSubscriptionPlan::query()->find($request->integer('plan')) : null;
        $startsAt = now()->toDateString();

        return view('web.admin.gym-platform-subscriptions.create', [
            'pageTitle' => 'Assign Gym Subscription',
            'breadcrumbs' => ['Platform', 'Gym Billing', 'Assign'],
            'subscription' => new GymPlatformSubscription([
                'gym_id' => $selectedGym?->id,
                'platform_subscription_plan_id' => $selectedPlan?->id,
                'status' => ($selectedPlan?->trial_days ?? 0) > 0 ? 'trialing' : 'active',
                'auto_renew' => true,
                'starts_at' => $startsAt,
                'trial_ends_at' => ($selectedPlan?->trial_days ?? 0) > 0 ? now()->addDays($selectedPlan->trial_days)->toDateString() : null,
                'renews_at' => $selectedPlan ? $this->resolveRenewalDate($startsAt, $selectedPlan)?->toDateString() : null,
                'billing_amount' => $selectedPlan?->price,
                'setup_fee_amount' => $selectedPlan?->setup_fee,
                'included_services' => $selectedPlan?->included_services ?? [],
                'plan_snapshot' => $selectedPlan ? $this->makePlanSnapshot($selectedPlan) : null,
            ]),
            'gyms' => Gym::query()->with('owner')->orderBy('name')->get(),
            'plans' => PlatformSubscriptionPlan::query()->where('status', 'active')->orWhere('is_default', true)->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreGymPlatformSubscriptionRequest $request): RedirectResponse
    {
        $subscription = DB::transaction(function () use ($request): GymPlatformSubscription {
            $validated = $request->validated();
            $plan = ! empty($validated['platform_subscription_plan_id'])
                ? PlatformSubscriptionPlan::query()->find($validated['platform_subscription_plan_id'])
                : null;

            $payload = $this->buildPayload($validated, $plan, $request->user()?->id);
            $subscription = GymPlatformSubscription::query()->create($payload);
            $invoice = $this->platformSubscriptionLedgerService->issueInitialInvoice($subscription->fresh(['gym', 'plan', 'invoices']), $request->user()?->id);

            $this->auditLogService->log(
                event: 'web.platform.gym-subscription.created',
                action: 'create',
                request: $request,
                subject: $subscription,
                gym: $subscription->gym,
                newValues: $subscription->toArray(),
                context: [
                    'invoice_id' => $invoice?->id,
                ],
            );

            return $subscription;
        });

        return redirect()
            ->route('web.admin.gym-platform-subscriptions.edit', $subscription)
            ->with('status', 'Gym billing subscription assigned successfully.');
    }

    public function edit(GymPlatformSubscription $gymPlatformSubscription): View
    {
        $this->platformSubscriptionLedgerService->syncInvoiceStatuses();

        $subscription = $gymPlatformSubscription->load([
            'gym.owner',
            'plan',
            'assignedBy',
            'invoices.generatedBy',
            'invoices.paidBy',
        ]);

        return view('web.admin.gym-platform-subscriptions.edit', [
            'pageTitle' => 'Edit Gym Subscription',
            'breadcrumbs' => ['Platform', 'Gym Billing', optional($gymPlatformSubscription->gym)->name ?? 'Subscription', 'Edit'],
            'subscription' => $subscription,
            'gyms' => Gym::query()->with('owner')->orderBy('name')->get(),
            'plans' => PlatformSubscriptionPlan::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'recentInvoices' => $subscription->invoices->sortByDesc('issued_at')->take(8)->values(),
            'invoiceSummary' => [
                'total_invoiced' => (float) $subscription->invoices->sum('total_amount'),
                'paid_revenue' => (float) $subscription->invoices->where('status', 'paid')->sum('total_amount'),
                'open_balance' => (float) $subscription->invoices->whereIn('status', ['due', 'overdue'])->sum('total_amount'),
                'invoice_count' => $subscription->invoices->count(),
            ],
        ]);
    }

    public function update(UpdateGymPlatformSubscriptionRequest $request, GymPlatformSubscription $gymPlatformSubscription): RedirectResponse
    {
        DB::transaction(function () use ($request, $gymPlatformSubscription): void {
            $validated = $request->validated();
            $plan = ! empty($validated['platform_subscription_plan_id'])
                ? PlatformSubscriptionPlan::query()->find($validated['platform_subscription_plan_id'])
                : null;
            $oldValues = $gymPlatformSubscription->toArray();

            $gymPlatformSubscription->update($this->buildPayload($validated, $plan, $request->user()?->id, $gymPlatformSubscription));
            $invoice = $this->platformSubscriptionLedgerService->issueInitialInvoice($gymPlatformSubscription->fresh(['gym', 'plan', 'invoices']), $request->user()?->id);

            $this->auditLogService->log(
                event: 'web.platform.gym-subscription.updated',
                action: 'update',
                request: $request,
                subject: $gymPlatformSubscription,
                gym: $gymPlatformSubscription->gym,
                oldValues: $oldValues,
                newValues: $gymPlatformSubscription->fresh()->toArray(),
                context: [
                    'invoice_id' => $invoice?->id,
                ],
            );
        });

        return back()->with('status', 'Gym billing subscription updated successfully.');
    }

    public function renew(Request $request, GymPlatformSubscription $gymPlatformSubscription): RedirectResponse
    {
        DB::transaction(function () use ($request, $gymPlatformSubscription): void {
            $gymPlatformSubscription->loadMissing(['gym', 'plan', 'invoices']);
            $oldValues = $gymPlatformSubscription->toArray();
            $anchorDate = $gymPlatformSubscription->renews_at && $gymPlatformSubscription->renews_at->isFuture()
                ? $gymPlatformSubscription->renews_at->toDateString()
                : now()->toDateString();
            $nextRenewalDate = $this->resolveRenewalDateForSubscription($anchorDate, $gymPlatformSubscription);

            $gymPlatformSubscription->forceFill([
                'status' => 'active',
                'starts_at' => $anchorDate,
                'renews_at' => $nextRenewalDate,
                'trial_ends_at' => null,
                'ends_at' => $gymPlatformSubscription->auto_renew ? null : $nextRenewalDate,
                'assigned_by_user_id' => $request->user()?->id ?? $gymPlatformSubscription->assigned_by_user_id,
                'notes' => trim(collect([
                    $gymPlatformSubscription->notes,
                    'Renewed on '.now()->format('d M Y, h:i A').' by '.($request->user()?->name ?? 'System'),
                ])->filter()->implode(PHP_EOL)),
            ])->save();

            $invoice = $this->platformSubscriptionLedgerService->issueRenewalInvoice(
                subscription: $gymPlatformSubscription->fresh(['gym', 'plan']),
                periodStartsAt: $anchorDate,
                periodEndsAt: (string) $nextRenewalDate,
                actorUserId: $request->user()?->id,
            );

            $this->auditLogService->log(
                event: 'web.platform.gym-subscription.renewed',
                action: 'update',
                request: $request,
                subject: $gymPlatformSubscription,
                gym: $gymPlatformSubscription->gym,
                oldValues: $oldValues,
                newValues: $gymPlatformSubscription->fresh()->toArray(),
                context: [
                    'invoice_id' => $invoice->id,
                    'cycle_start' => $anchorDate,
                    'cycle_end' => $nextRenewalDate,
                ],
            );
        });

        return back()->with('status', 'Gym subscription renewed successfully.');
    }

    public function markInvoicePaid(Request $request, GymPlatformSubscriptionInvoice $gymPlatformSubscriptionInvoice): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request, $gymPlatformSubscriptionInvoice): GymPlatformSubscriptionInvoice {
            $gymPlatformSubscriptionInvoice->loadMissing(['gym', 'subscription', 'plan']);
            $oldValues = $gymPlatformSubscriptionInvoice->toArray();
            $invoice = $this->platformSubscriptionLedgerService->markPaid(
                invoice: $gymPlatformSubscriptionInvoice,
                actorUserId: $request->user()?->id,
                paymentReference: $request->string('payment_reference')->trim()->value() ?: null,
                paymentNotes: $request->string('payment_notes')->trim()->value() ?: null,
                paidAt: $request->filled('paid_at') ? $request->string('paid_at')->value() : null,
            );

            $this->auditLogService->log(
                event: 'web.platform.gym-subscription.invoice-paid',
                action: 'update',
                request: $request,
                subject: $invoice,
                gym: $invoice->gym,
                oldValues: $oldValues,
                newValues: $invoice->toArray(),
                context: [
                    'subscription_id' => $invoice->gym_platform_subscription_id,
                ],
            );

            return $invoice;
        });

        return back()->with('status', 'Platform invoice '.$invoice->invoice_number.' marked as paid.');
    }

    public function ledger(Request $request, GymPlatformSubscription $gymPlatformSubscription): View
    {
        $this->platformSubscriptionLedgerService->syncInvoiceStatuses();

        $subscription = $gymPlatformSubscription->load(['gym.owner', 'plan', 'assignedBy']);
        $invoiceQuery = $gymPlatformSubscription->invoices()
            ->with(['plan', 'generatedBy', 'paidBy'])
            ->latest('issued_at');

        return view('web.admin.gym-platform-subscriptions.ledger', [
            'pageTitle' => 'Billing Ledger',
            'breadcrumbs' => ['Platform', 'Gym Billing', optional($subscription->gym)->name ?? 'Subscription', 'Ledger'],
            'subscription' => $subscription,
            'invoices' => $invoiceQuery->paginate(15)->withQueryString(),
            'invoiceSummary' => [
                'total_invoiced' => (float) (clone $invoiceQuery)->sum('total_amount'),
                'paid_revenue' => (float) (clone $invoiceQuery)->where('status', 'paid')->sum('total_amount'),
                'open_balance' => (float) (clone $invoiceQuery)->whereIn('status', ['due', 'overdue'])->sum('total_amount'),
                'overdue_balance' => (float) (clone $invoiceQuery)->where('status', 'overdue')->sum('total_amount'),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPayload(array $validated, ?PlatformSubscriptionPlan $plan, ?int $actorId, ?GymPlatformSubscription $existing = null): array
    {
        $planServices = $plan?->included_services ?? [];
        $existingServices = $existing?->included_services ?? [];
        $includedServices = $validated['included_services'] ?: ($planServices ?: $existingServices);
        $startsAt = $validated['starts_at'] ?? $existing?->starts_at?->toDateString();
        $resolvedRenewalDate = array_key_exists('renews_at', $validated) && ! empty($validated['renews_at'])
            ? $validated['renews_at']
            : ($plan && $startsAt ? $this->resolveRenewalDate($startsAt, $plan)?->toDateString() : ($existing?->renews_at?->toDateString()));

        return [
            'gym_id' => $validated['gym_id'],
            'platform_subscription_plan_id' => $plan?->id,
            'assigned_by_user_id' => $actorId ?? $existing?->assigned_by_user_id,
            'status' => $validated['status'],
            'starts_at' => $startsAt,
            'renews_at' => $resolvedRenewalDate,
            'ends_at' => $validated['ends_at'] ?? $existing?->ends_at,
            'trial_ends_at' => $validated['trial_ends_at'] ?? $existing?->trial_ends_at,
            'billing_amount' => $validated['billing_amount'] ?? $plan?->price ?? $existing?->billing_amount ?? 0,
            'setup_fee_amount' => $validated['setup_fee_amount'] ?? $plan?->setup_fee ?? $existing?->setup_fee_amount ?? 0,
            'auto_renew' => $validated['auto_renew'] ?? true,
            'included_services' => $includedServices,
            'plan_snapshot' => $plan ? $this->makePlanSnapshot($plan) : null,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : ($existing?->notes),
        ];
    }

    private function makePlanSnapshot(PlatformSubscriptionPlan $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'cadence_label' => $plan->cadence_label,
            'price_label' => $plan->price_label,
            'trial_days' => $plan->trial_days,
            'included_services' => $plan->included_services ?? [],
            'feature_highlights' => $plan->feature_highlights ?? [],
        ];
    }

    private function resolveRenewalDate(string $startsAt, PlatformSubscriptionPlan $plan): ?CarbonImmutable
    {
        $start = CarbonImmutable::parse($startsAt);

        return match ($plan->billing_period) {
            'day' => $start->addDays($plan->billing_interval_count),
            'week' => $start->addWeeks($plan->billing_interval_count),
            'month' => $start->addMonths($plan->billing_interval_count),
            'quarter' => $start->addMonths($plan->billing_interval_count * 3),
            'year' => $start->addYears($plan->billing_interval_count),
            default => null,
        };
    }

    private function resolveRenewalDateForSubscription(string $startsAt, GymPlatformSubscription $subscription): ?string
    {
        if ($subscription->plan) {
            return $this->resolveRenewalDate($startsAt, $subscription->plan)?->toDateString();
        }

        $existingWindowDays = $subscription->starts_at && $subscription->renews_at
            ? max(1, $subscription->starts_at->diffInDays($subscription->renews_at))
            : 30;

        return CarbonImmutable::parse($startsAt)->addDays($existingWindowDays)->toDateString();
    }
}
