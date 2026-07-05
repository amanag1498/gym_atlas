<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Platform\StorePlatformSubscriptionPlanRequest;
use App\Http\Requests\Web\Platform\UpdatePlatformSubscriptionPlanRequest;
use App\Models\PlatformSubscriptionPlan;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformSubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): View
    {
        $query = PlatformSubscriptionPlan::query()
            ->withCount('gymSubscriptions')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('slug', 'like', $search)
                ->orWhere('description', 'like', $search));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('web.admin.platform-subscription-plans.index', [
            'pageTitle' => 'Platform Plans',
            'breadcrumbs' => ['Platform', 'Plans'],
            'plans' => $query->paginate(12)->withQueryString(),
            'totalPlansCount' => PlatformSubscriptionPlan::query()->count(),
            'defaultPlansCount' => PlatformSubscriptionPlan::query()->where('is_default', true)->count(),
            'activePlansCount' => PlatformSubscriptionPlan::query()->where('status', 'active')->count(),
            'assignedPlansCount' => DB::table('gym_platform_subscriptions')->count(),
        ]);
    }

    public function create(): View
    {
        return view('web.admin.platform-subscription-plans.create', [
            'pageTitle' => 'Create Platform Plan',
            'breadcrumbs' => ['Platform', 'Plans', 'Create'],
            'plan' => new PlatformSubscriptionPlan([
                'status' => 'active',
                'billing_period' => 'month',
                'billing_interval_count' => 1,
                'trial_days' => 0,
                'price' => 0,
                'setup_fee' => 0,
            ]),
        ]);
    }

    public function store(StorePlatformSubscriptionPlanRequest $request): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request): PlatformSubscriptionPlan {
            $validated = $request->validated();

            if ($validated['is_default'] ?? false) {
                PlatformSubscriptionPlan::query()->update(['is_default' => false]);
            }

            $plan = PlatformSubscriptionPlan::query()->create($validated);

            $this->auditLogService->log(
                event: 'web.platform.subscription-plan.created',
                action: 'create',
                request: $request,
                subject: $plan,
                newValues: $plan->toArray(),
            );

            return $plan;
        });

        return redirect()
            ->route('web.admin.platform-subscription-plans.edit', $plan)
            ->with('status', 'Platform plan created successfully.');
    }

    public function edit(PlatformSubscriptionPlan $platformSubscriptionPlan): View
    {
        return view('web.admin.platform-subscription-plans.edit', [
            'pageTitle' => 'Edit Platform Plan',
            'breadcrumbs' => ['Platform', 'Plans', $platformSubscriptionPlan->name, 'Edit'],
            'plan' => $platformSubscriptionPlan->loadCount('gymSubscriptions'),
        ]);
    }

    public function update(UpdatePlatformSubscriptionPlanRequest $request, PlatformSubscriptionPlan $platformSubscriptionPlan): RedirectResponse
    {
        DB::transaction(function () use ($request, $platformSubscriptionPlan): void {
            $validated = $request->validated();
            $oldValues = $platformSubscriptionPlan->toArray();

            if ($validated['is_default'] ?? false) {
                PlatformSubscriptionPlan::query()
                    ->whereKeyNot($platformSubscriptionPlan->id)
                    ->update(['is_default' => false]);
            }

            $platformSubscriptionPlan->update($validated);

            $this->auditLogService->log(
                event: 'web.platform.subscription-plan.updated',
                action: 'update',
                request: $request,
                subject: $platformSubscriptionPlan,
                oldValues: $oldValues,
                newValues: $platformSubscriptionPlan->fresh()->toArray(),
            );
        });

        return back()->with('status', 'Platform plan updated successfully.');
    }
}
