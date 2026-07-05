<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTrialRequestRequest;
use App\Http\Resources\Discovery\TrialRequestResource;
use App\Services\Trials\TrialRequestService;

class TrialRequestController extends Controller
{
    public function __construct(
        private readonly TrialRequestService $trialRequestService,
    ) {
    }

    public function store(StoreTrialRequestRequest $request)
    {
        $trialRequest = $this->trialRequestService->createForMember(
            $request->user(),
            $request->validated(),
            $request,
        );

        return $this->success(TrialRequestResource::make($trialRequest), 'Trial request created successfully.', 201);
    }
}
