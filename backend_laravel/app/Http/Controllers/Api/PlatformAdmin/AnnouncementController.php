<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Http\Resources\Communication\AnnouncementResource;
use App\Services\Communication\AnnouncementService;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->announcementService->listAnnouncementsForActor($request->user());

        return $this->paginated($paginator, AnnouncementResource::collection($paginator->getCollection()), 'Platform announcements fetched successfully.');
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $payload = $request->validated();
        $payload['audience_type'] = $payload['audience_type'] ?? 'platform_wide';

        $announcement = $this->announcementService->createAnnouncement($request->user(), $payload);

        return $this->success(AnnouncementResource::make($announcement), 'Platform announcement created successfully.', 201);
    }
}
