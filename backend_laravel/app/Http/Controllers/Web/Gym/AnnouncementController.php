<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Models\Branch;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Communication\AnnouncementService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Services\Notification\NotificationService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $gymWebPanelService,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
        private readonly AnnouncementService $announcementService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AnnouncementsView->value, $gym);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym);

        return view('web.gym.announcements.index', [
            'pageTitle' => 'Announcements',
            'breadcrumbs' => ['Gym', 'Announcements'],
            'gym' => $gym,
            'branch' => $branch,
            'announcements' => $this->announcementService
                ->listAnnouncementsForActor($request->user(), $gym->id, $branch?->id)
                ->withQueryString(),
            'branches' => $gym->branches()
                ->whereIn('id', $this->gymWebPanelService->accessibleBranchIds($request, $gym))
                ->orderBy('name')
                ->get(),
            'notifications' => $this->notificationQuery($request, $gym, $branch?->id)->paginate(12, ['*'], 'notifications_page')->withQueryString(),
            'unreadNotificationsCount' => (clone $this->notificationQuery($request, $gym, $branch?->id))->whereNull('read_at')->count(),
            'members' => User::query()
                ->whereHas('memberProfile', function ($builder) use ($gym, $branch): void {
                    $builder->where('gym_id', $gym->id)
                        ->when($branch, fn ($query) => $query->where('branch_id', $branch->id));
                })
                ->orderBy('name')
                ->get(),
            'canSendAnnouncements' => $this->canSendAnnouncements($request, $gym, $branch?->id),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->index($request);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AnnouncementsView->value, $gym);

        return view('web.gym.announcements.show', [
            'pageTitle' => $announcement->title,
            'breadcrumbs' => ['Gym', 'Announcements', $announcement->title],
            'announcement' => $this->announcementService->resolveAnnouncementForActor($request->user(), $announcement)->load('creator', 'gym', 'branch'),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branch = $request->filled('branch_id')
            ? Branch::query()->whereKey($request->integer('branch_id'))->firstOrFail()
            : null;

        if ($branch) {
            $this->gymWebPanelService->assertBranchAccessible($branch, $request, $gym);
        }

        $this->gymWebPanelService->assertPermission($request, PermissionName::AnnouncementsManage->value, $gym, $branch?->id);
        $this->assertSendAnnouncementsAccess($request, $gym, $branch?->id);

        $payload = $request->validated();
        $payload['gym_id'] = $gym->id;
        $payload['branch_id'] = $branch?->id;

        $this->announcementService->createAnnouncement($request->user(), $payload);

        return back()->with('status', 'Announcement created successfully.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $branchId = $announcement->branch_id;
        $this->gymWebPanelService->assertPermission($request, PermissionName::AnnouncementsManage->value, $gym, $branchId);
        $this->assertSendAnnouncementsAccess($request, $gym, $branchId);
        $this->announcementService->deleteAnnouncement($request->user(), $announcement);

        return redirect()->route('web.gym.announcements.index', request()->only(['gym', 'branch']))
            ->with('status', 'Announcement deleted successfully.');
    }

    public function notifications(Request $request): View
    {
        $gym = $this->gymWebPanelService->resolveGym($request);
        $this->gymWebPanelService->assertPermission($request, PermissionName::AnnouncementsView->value, $gym);
        $branch = $this->gymWebPanelService->resolveBranch($request, $gym);

        return view('web.gym.notifications.index', [
            'pageTitle' => 'Notifications',
            'breadcrumbs' => ['Gym', 'Notifications'],
            'notifications' => $this->notificationQuery($request, $gym, $branch?->id)->paginate(15)->withQueryString(),
            'unreadNotificationsCount' => (clone $this->notificationQuery($request, $gym, $branch?->id))->whereNull('read_at')->count(),
        ]);
    }

    private function canSendAnnouncements(Request $request, $gym, ?int $branchId = null): bool
    {
        if (! $this->gymWebPanelService->canPermission($request, PermissionName::AnnouncementsManage->value, $gym, $branchId)) {
            return false;
        }

        $user = $request->user();

        if ($user->active_role !== RoleName::GymStaff->value) {
            return true;
        }

        return $this->scopedPermissionResolver->hasCustomPermission($user, 'send_announcements', $gym->id, $branchId);
    }

    private function assertSendAnnouncementsAccess(Request $request, $gym, ?int $branchId = null): void
    {
        if (! $this->canSendAnnouncements($request, $gym, $branchId)) {
            abort(403, 'You do not have permission to send announcements in this scope.');
        }
    }

    private function notificationQuery(Request $request, $gym, ?int $branchId = null)
    {
        return \App\Models\Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('gym_id', $gym->id)
            ->when($branchId !== null, fn ($query) => $query->where(function ($builder) use ($branchId): void {
                $builder->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->latest('id');
    }
}
