<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DietPlan;
use App\Models\Gym;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DietPlanController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): View
    {
        $query = DietPlan::query()->with(['gym', 'branch', 'member', 'trainer', 'creator', 'meals']);
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($nested) => $nested->where('name', 'like', $search)
                ->orWhereHas('member', fn ($members) => $members->where('name', 'like', $search)->orWhere('email', 'like', $search))
                ->orWhereHas('gym', fn ($gyms) => $gyms->where('name', 'like', $search)));
        }
        $query->when($request->filled('gym_id'), fn ($builder) => $builder->where('gym_id', $request->integer('gym_id')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()));
        $plans = $query->latest()->paginate(20)->withQueryString();

        return view('web.admin.diet-plans.index', [
            'pageTitle' => 'Diet Plans', 'breadcrumbs' => ['Platform', 'Diet Plans'], 'plans' => $plans,
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'activeCount' => DietPlan::query()->where('status', 'active')->count(),
            'personalCount' => DietPlan::query()->whereNull('trainer_id')->count(),
        ]);
    }

    public function show(DietPlan $dietPlan): View
    {
        return view('web.admin.diet-plans.show', [
            'pageTitle' => $dietPlan->name,
            'breadcrumbs' => ['Platform', 'Diet Plans', $dietPlan->name],
            'plan' => $dietPlan->load([
                'gym',
                'branch',
                'member',
                'trainer',
                'creator',
                'meals.items',
            ]),
        ]);
    }

    public function updateStatus(Request $request, DietPlan $dietPlan): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]])['status'];
        $oldValues = $dietPlan->only('status');
        $dietPlan->update(['status' => $status]);
        $this->auditLogService->log(event: 'web.admin.diet_plan.status_updated', action: 'update', request: $request, subject: $dietPlan, gym: $dietPlan->gym, branch: $dietPlan->branch, oldValues: $oldValues, newValues: $dietPlan->only('status'));

        return back()->with('status', 'Diet plan status updated.');
    }
}
