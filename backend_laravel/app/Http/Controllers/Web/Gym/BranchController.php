<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Gym\StoreBranchWebRequest;
use App\Http\Requests\Web\Gym\UpdateBranchWebRequest;
use App\Models\Branch;
use App\Models\City;
use App\Models\Facility;
use App\Services\Audit\AuditLogService;
use App\Services\Gym\BranchManagementService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly AuditLogService $auditLogService,
        private readonly BranchManagementService $branchManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::BranchesView->value, $gym);
        $canManageBranches = $this->canManageBranches($request, $gym);
        $query = Branch::query()
            ->with(['facilities', 'cityRecord'])
            ->withCount([
                'memberProfiles',
                'trainerProfiles',
                'membershipPlans',
                'trialRequests',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ])
            ->where('gym_id', $gym->id)
            ->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $search)
                ->orWhere('city', 'like', $search)
                ->orWhere('pincode', 'like', $search));
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        if ($branch = $this->gymWebPanelService->resolveBranch($request, $gym)) {
            $query->whereKey($branch->id);
        } else {
            $query->whereIn('id', $this->gymWebPanelService->accessibleBranchIds($request, $gym));
        }

        return view('web.gym.branches.index', [
            'pageTitle' => 'Branches',
            'breadcrumbs' => ['Gym', 'Branches'],
            'gym' => $gym,
            'branches' => $query->paginate(12)->withQueryString(),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'cities' => City::query()->orderBy('name')->get(),
            'canManageBranches' => $canManageBranches,
        ]);
    }

    public function create(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym);

        return view('web.gym.branches.create', [
            'pageTitle' => 'Create Branch',
            'breadcrumbs' => ['Gym', 'Branches', 'Create'],
            'gym' => $gym,
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'cities' => City::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreBranchWebRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym);
        $branch = $this->branchManagementService->create($request, $gym, $request->validated());

        $this->auditLogService->log(
            event: 'web.gym.branch.created',
            action: 'create',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            newValues: $branch->fresh('facilities')->toArray(),
        );

        return redirect()
            ->route('web.gym.branches.show', ['branch' => $branch->id, 'gym' => $gym->id])
            ->with('status', 'Branch created successfully.');
    }

    public function show(Request $request, Branch $branch): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::BranchesView->value, $gym, $branch->id);
        $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);

        return view('web.gym.branches.show', [
            'pageTitle' => 'Branch Detail',
            'breadcrumbs' => ['Gym', 'Branches', $branch->name],
            'gym' => $gym,
            'branch' => $branch->load(['facilities', 'cityRecord'])->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'membershipPlans',
                'trialRequests',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]),
            'recentPlans' => $branch->membershipPlans()->latest('id')->take(6)->get(),
            'canManageBranches' => $this->canManageBranches($request, $gym, $branch->id),
        ]);
    }

    public function edit(Request $request, Branch $branch): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym, $branch->id);
        $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);

        return view('web.gym.branches.edit', [
            'pageTitle' => 'Edit Branch',
            'breadcrumbs' => ['Gym', 'Branches', $branch->name, 'Edit'],
            'gym' => $gym,
            'branch' => $branch->load(['facilities', 'cityRecord'])->loadCount([
                'memberProfiles',
                'trainerProfiles',
                'membershipPlans',
                'trialRequests',
                'attendanceLogs as today_check_ins_count' => fn ($query) => $query->whereDate('checked_in_at', today()),
            ]),
            'facilities' => Facility::query()->where('is_active', true)->orderBy('name')->get(),
            'cities' => City::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateBranchWebRequest $request, Branch $branch): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym, $branch->id);
        $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);

        $oldValues = $branch->load('facilities')->toArray();
        $branch = $this->branchManagementService->update($branch, $request->validated());

        $this->auditLogService->log(
            event: 'web.gym.branch.updated',
            action: 'update',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $branch->fresh('facilities')->toArray(),
        );

        return redirect()
            ->route('web.gym.branches.show', ['branch' => $branch->id, 'gym' => $gym->id])
            ->with('status', 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym, $branch->id);
        $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);

        if (! $this->branchManagementService->canDeleteSafely($branch)) {
            return back()->withErrors([
                'branch' => 'This branch has active members. Deactivate it instead of deleting it.',
            ]);
        }

        $oldValues = $branch->toArray();
        $branch->delete();

        $this->auditLogService->log(
            event: 'web.gym.branch.deleted',
            action: 'delete',
            request: $request,
            subject: $branch,
            gym: $gym,
            oldValues: $oldValues,
        );

        return back()->with('status', 'Branch deleted successfully.');
    }

    public function toggleStatus(Request $request, Branch $branch): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->assertManageAllowed($request, $gym, $branch->id);
        $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);

        $oldValues = $branch->only(['is_active', 'status']);
        $branch = $this->branchManagementService->toggleStatus($branch);

        $this->auditLogService->log(
            event: 'web.gym.branch.status.updated',
            action: 'update',
            request: $request,
            subject: $branch,
            gym: $gym,
            branch: $branch,
            oldValues: $oldValues,
            newValues: $branch->only(['is_active', 'status']),
        );

        return back()->with('status', 'Branch status updated successfully.');
    }

    private function canManageBranches(Request $request, \App\Models\Gym $gym, ?int $branchId = null): bool
    {
        if ($request->user()?->active_role === \App\Enums\RoleName::BranchManager->value) {
            return false;
        }

        return $this->gymWebPanelService->canPermission($request, PermissionName::BranchesManage->value, $gym, $branchId);
    }

    private function assertManageAllowed(Request $request, \App\Models\Gym $gym, ?int $branchId = null): void
    {
        abort_unless($this->canManageBranches($request, $gym, $branchId), 403);
    }
}
