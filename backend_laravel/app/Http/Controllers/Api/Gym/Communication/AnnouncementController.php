<?php

namespace App\Http\Controllers\Api\Gym\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Http\Resources\Communication\AnnouncementResource;
use App\Models\Announcement;
use App\Services\Communication\AnnouncementService;
use App\Services\Authorization\ScopedPermissionResolver;
use App\Enums\RoleName;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
        private readonly ScopedPermissionResolver $scopedPermissionResolver,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->announcementService->listAnnouncementsForActor(
            actor: $request->user(),
            gymId: $request->integer('gym_id') ?: null,
            branchId: $request->integer('branch_id') ?: null,
        );

        return $this->paginated($paginator, AnnouncementResource::collection($paginator->getCollection()), 'Announcements fetched successfully.');
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $this->assertSendAnnouncementsAccess($request, $request->integer('gym_id') ?: null, $request->integer('branch_id') ?: null);
        $announcement = $this->announcementService->createAnnouncement($request->user(), $request->validated());

        return $this->success(AnnouncementResource::make($announcement), 'Announcement created successfully.', 201);
    }

    public function show(Request $request, Announcement $announcement)
    {
        return $this->success(
            AnnouncementResource::make(
                $this->announcementService->resolveAnnouncementForActor($request->user(), $announcement)->loadCount('recipients')
            ),
            'Announcement fetched successfully.'
        );
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $resolved = $this->announcementService->resolveAnnouncementForActor($request->user(), $announcement);
        $this->assertSendAnnouncementsAccess($request, $resolved->gym_id, $resolved->branch_id);
        $this->announcementService->deleteAnnouncement($request->user(), $announcement);

        return $this->success(null, 'Announcement deleted successfully.');
    }

    private function assertSendAnnouncementsAccess(Request $request, ?int $gymId = null, ?int $branchId = null): void
    {
        $user = $request->user();

        if ($user->active_role !== RoleName::GymStaff->value) {
            return;
        }

        if (! $this->scopedPermissionResolver->hasCustomPermission($user, 'send_announcements', $gymId, $branchId)) {
            throw new HttpException(403, 'You do not have permission to send announcements in this scope.');
        }
    }
}
