<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\AnnouncementAudienceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communication\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {
    }

    public function index(Request $request): View
    {
        return view('web.admin.announcements.index', [
            'pageTitle' => 'Platform Announcements',
            'breadcrumbs' => ['Platform', 'Announcements'],
            'announcements' => $this->announcementService->listAnnouncementsForActor(
                $request->user(),
                filters: $request->only(['search', 'audience_type']),
            ),
            'platformWideCount' => Announcement::query()->where('is_platform_wide', true)->count(),
            'targetedCount' => Announcement::query()->where('is_platform_wide', false)->count(),
            'recipientCount' => AnnouncementRecipient::query()->count(),
            'readCount' => AnnouncementRecipient::query()->whereNotNull('read_at')->count(),
            'audienceOptions' => [
                '' => 'All audiences',
                AnnouncementAudienceType::PlatformWide->value => 'All app users',
                AnnouncementAudienceType::GymWide->value => 'All members in one gym',
                AnnouncementAudienceType::BranchSpecific->value => 'One branch audience',
                AnnouncementAudienceType::SelectedMembers->value => 'Selected members only',
                AnnouncementAudienceType::Offer->value => 'Offer / campaign audience',
            ],
        ]);
    }

    public function create(): View
    {
        return view('web.admin.announcements.create', [
            'pageTitle' => 'Send Platform Announcement',
            'breadcrumbs' => ['Platform', 'Announcements', 'Create'],
            'audienceOptions' => [
                AnnouncementAudienceType::PlatformWide->value => 'All app users',
                AnnouncementAudienceType::GymWide->value => 'All members in one gym',
                AnnouncementAudienceType::BranchSpecific->value => 'One branch audience',
                AnnouncementAudienceType::SelectedMembers->value => 'Selected members only',
                AnnouncementAudienceType::Offer->value => 'Offer / campaign audience',
            ],
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->with('gym:id,name')->orderBy('name')->get(['id', 'gym_id', 'name']),
            'members' => User::query()->role(\App\Enums\RoleName::Member->value)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function notifications(Request $request): View
    {
        $query = Notification::query()
            ->with(['user', 'gym', 'branch', 'creator', 'announcement'])
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', $search)
                        ->orWhere('message', 'like', $search)
                        ->orWhere('body', 'like', $search)
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('name', 'like', $search)
                                ->orWhere('email', 'like', $search);
                        });
                });
            })
            ->when($request->filled('type'), fn ($builder) => $builder->where('type', $request->string('type')->toString()))
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $request->string('status')->toString() === 'unread'
                    ? $builder->whereNull('read_at')
                    : $builder->whereNotNull('read_at');
            })
            ->latest('id');

        return view('web.admin.notifications.index', [
            'pageTitle' => 'Platform Notifications',
            'breadcrumbs' => ['Platform', 'Notifications'],
            'notifications' => $query->paginate(15)->withQueryString(),
            'notificationTypes' => Notification::query()
                ->whereNotNull('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values()
                ->all(),
            'scheduledCount' => Notification::query()->whereNotNull('scheduled_for')->count(),
            'unreadNotificationsCount' => Notification::query()->whereNull('read_at')->count(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $payload = $request->validated();

        $this->announcementService->createAnnouncement($request->user(), $payload);

        return redirect()
            ->route('web.admin.announcements.index')
            ->with('status', 'Platform announcement sent successfully.');
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $announcement = $this->announcementService->showAnnouncementForActor($request->user(), $announcement);

        return view('web.admin.announcements.show', [
            'pageTitle' => $announcement->title,
            'breadcrumbs' => ['Platform', 'Announcements', $announcement->title],
            'announcement' => $announcement,
        ]);
    }
}
