<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\AnnouncementAudienceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Http\Resources\Communication\AnnouncementResource;
use App\Services\Communication\AnnouncementService;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $payload = $request->validated();
        $payload['audience_type'] = $payload['audience_type'] ?? AnnouncementAudienceType::SelectedMembers->value;

        $announcement = $this->announcementService->createAnnouncement($request->user(), $payload);

        return $this->success(AnnouncementResource::make($announcement), 'Trainer notification created successfully.', 201);
    }
}
