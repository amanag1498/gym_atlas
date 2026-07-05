<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trial\UpdateTrialRequestRequest;
use App\Http\Resources\Discovery\TrialRequestResource;
use App\Models\TrialRequest;
use App\Services\Trials\TrialRequestService;
use Illuminate\Http\Request;

class TrialRequestController extends Controller
{
    public function __construct(
        private readonly TrialRequestService $trialRequestService,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->trialRequestService->queryForActor($request->user(), $request)
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, TrialRequestResource::collection($paginator->getCollection()), 'Assigned trial requests fetched successfully.');
    }

    public function show(Request $request, TrialRequest $trialRequest)
    {
        $trialRequest = $this->trialRequestService->resolveForActor($request->user(), $trialRequest);

        return $this->success(TrialRequestResource::make($trialRequest));
    }

    public function update(UpdateTrialRequestRequest $request, TrialRequest $trialRequest)
    {
        $updated = $this->trialRequestService->updateForActor($request->user(), $trialRequest, $request->validated(), $request);

        return $this->success(TrialRequestResource::make($updated), 'Trial request updated successfully.');
    }
}
