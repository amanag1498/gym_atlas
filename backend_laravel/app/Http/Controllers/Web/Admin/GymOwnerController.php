<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformAdmin\StoreGymOwnerRequest;
use App\Http\Requests\PlatformAdmin\UpdateGymOwnerRequest;
use App\Models\Gym;
use App\Models\User;
use App\Services\Audit\AdminActivityFeedService;
use App\Services\Platform\PlatformAuditLogService;
use App\Services\Platform\PlatformGymOwnerManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GymOwnerController extends Controller
{
    public function __construct(
        private readonly PlatformGymOwnerManagementService $gymOwnerService,
        private readonly AdminActivityFeedService $adminActivityFeedService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {}

    public function index(Request $request): View
    {
        $owners = $this->gymOwnerService->query($request)->paginate(15)->withQueryString();

        return view('web.admin.gym-owners.index', [
            'pageTitle' => 'Gym Owners',
            'breadcrumbs' => ['Platform', 'Gym Owners'],
            'owners' => $owners,
            'hasPhoneColumn' => $this->gymOwnerService->hasPhoneColumn(),
        ]);
    }

    public function create(): View
    {
        return view('web.admin.gym-owners.create', [
            'pageTitle' => 'Add Gym Owner',
            'breadcrumbs' => ['Platform', 'Gym Owners', 'Add Gym Owner'],
            'owner' => new User(['is_active' => true]),
            'hasPhoneColumn' => $this->gymOwnerService->hasPhoneColumn(),
            'isEdit' => false,
        ]);
    }

    public function store(StoreGymOwnerRequest $request): RedirectResponse
    {
        $result = $this->gymOwnerService->create($request, $request->validated());

        return redirect()
            ->route('web.admin.gym-owners.show', $result['owner'])
            ->with('status', 'Gym Owner created successfully. Temporary password: '.$result['temporary_password'])
            ->with('owner_temp_password', $result['temporary_password']);
    }

    public function show(User $user): View
    {
        $owner = $this->gymOwnerService->loadDetail($user);
        $activityFeed = $this->adminActivityFeedService->build($owner->activityLogs);

        return view('web.admin.gym-owners.show', [
            'pageTitle' => $owner->name,
            'breadcrumbs' => ['Platform', 'Gym Owners', $owner->name],
            'owner' => $owner,
            'activityTimeline' => $activityFeed['timeline'],
            'activityStats' => $activityFeed['stats'],
            'activityRows' => $activityFeed['rows'],
            'activityLatestLabel' => $activityFeed['latest_label'],
            'hasPhoneColumn' => $this->gymOwnerService->hasPhoneColumn(),
        ]);
    }

    public function activity(Request $request, User $user): View
    {
        $owner = $this->gymOwnerService->loadDetail($user);
        $query = $this->gymOwnerService->activityQuery($owner, $owner->ownedGyms->pluck('id')->all());

        if ($request->filled('action')) {
            $action = '%'.$request->string('action')->trim().'%';
            $query->where(function ($builder) use ($action): void {
                $builder->where('action', 'like', $action)
                    ->orWhere('event', 'like', $action);
            });
        }

        if ($request->date('start_date')) {
            $startDate = $request->date('start_date')->startOfDay();
            $query->where(function ($builder) use ($startDate): void {
                $builder->where('occurred_at', '>=', $startDate)
                    ->orWhere(function ($nested) use ($startDate): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '>=', $startDate);
                    });
            });
        }

        if ($request->date('end_date')) {
            $endDate = $request->date('end_date')->endOfDay();
            $query->where(function ($builder) use ($endDate): void {
                $builder->where('occurred_at', '<=', $endDate)
                    ->orWhere(function ($nested) use ($endDate): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '<=', $endDate);
                    });
            });
        }

        $auditLogs = $query->paginate(20)->withQueryString();

        return view('web.admin.gym-owners.activity', [
            'pageTitle' => $owner->name.' Activity',
            'breadcrumbs' => ['Platform', 'Gym Owners', $owner->name, 'Activity'],
            'owner' => $owner,
            'auditLogs' => $auditLogs,
            'filters' => [
                'action' => $request->string('action')->toString(),
                'start_date' => $request->string('start_date')->toString(),
                'end_date' => $request->string('end_date')->toString(),
            ],
            'sanitizer' => $this->platformAuditLogService,
            'hasPhoneColumn' => $this->gymOwnerService->hasPhoneColumn(),
        ]);
    }

    public function mockDashboard(Request $request, User $user): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        abort_unless($admin?->hasRole(\App\Enums\RoleName::PlatformAdmin->value), 403);

        $this->gymOwnerService->ensureGymOwner($user);

        $routeGym = $request->route('gym');
        $gym = $routeGym
            ? ($routeGym instanceof Gym ? $routeGym : Gym::query()->findOrFail($routeGym))
            : $user->ownedGyms()
                ->orderByDesc('is_active')
                ->latest('id')
                ->first();

        if (! $gym) {
            throw ValidationException::withMessages([
                'gym' => ['This gym owner does not have any gym assigned yet.'],
            ]);
        }

        abort_unless((int) $gym->owner_user_id === (int) $user->id, 404);

        $user->forceFill(['active_role' => \App\Enums\RoleName::GymOwner->value])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('web_panel.gym_id', $gym->id);
        $request->session()->put('web_panel.platform_admin_impersonator_id', $admin->id);
        $request->session()->put('web_panel.platform_admin_return_url', route('web.admin.gym-owners.show', $user));
        $request->session()->forget('web_panel.branch_id');

        return redirect()
            ->route('web.gym.dashboard', ['gym' => $gym->id])
            ->with('status', 'Viewing '.$gym->name.' as '.$user->name.'.');
    }

    public function stopMockDashboard(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('web_panel.platform_admin_impersonator_id');
        abort_unless($adminId, 403);

        /** @var User $admin */
        $admin = User::query()->findOrFail($adminId);
        abort_unless($admin->hasRole(\App\Enums\RoleName::PlatformAdmin->value), 403);

        $returnUrl = $request->session()->get('web_panel.platform_admin_return_url')
            ?: route('web.admin.gym-owners.index');

        Auth::guard('web')->login($admin);
        $request->session()->regenerate();
        $request->session()->forget('web_panel');

        return redirect($returnUrl)->with('status', 'Returned to platform admin.');
    }

    public function edit(User $user): View
    {
        $this->gymOwnerService->ensureGymOwner($user);

        return view('web.admin.gym-owners.edit', [
            'pageTitle' => 'Edit Gym Owner',
            'breadcrumbs' => ['Platform', 'Gym Owners', $user->name, 'Edit'],
            'owner' => $user,
            'hasPhoneColumn' => $this->gymOwnerService->hasPhoneColumn(),
            'isEdit' => true,
        ]);
    }

    public function update(UpdateGymOwnerRequest $request, User $user): RedirectResponse
    {
        $owner = $this->gymOwnerService->update($request, $user, $request->validated());

        return redirect()
            ->route('web.admin.gym-owners.show', $owner)
            ->with('status', 'Gym Owner updated successfully.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $owner = $this->gymOwnerService->activate($request, $user);

        return redirect()
            ->route('web.admin.gym-owners.show', $owner)
            ->with('status', 'Gym Owner activated successfully.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $owner = $this->gymOwnerService->deactivate(
            $request,
            $user,
            $request->boolean('confirm_orphan_active_gyms'),
        );

        return redirect()
            ->route('web.admin.gym-owners.show', $owner)
            ->with('status', 'Gym Owner deactivated successfully.');
    }
}
