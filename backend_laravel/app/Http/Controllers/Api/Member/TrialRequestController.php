<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTrialRequestRequest;
use App\Http\Resources\Discovery\TrialRequestResource;
use App\Models\TrialRequest;
use App\Services\Trials\TrialRequestService;
use Illuminate\Http\Request;

class TrialRequestController extends Controller
{
    public function __construct(
        private readonly TrialRequestService $trialRequestService,
    ) {}

    public function index(Request $request)
    {
        $paginator = TrialRequest::query()
            ->with(['gym', 'branch', 'member', 'assignedTrainer'])
            ->where('member_id', $request->user()->id)
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, TrialRequestResource::collection($paginator->getCollection()), 'Trial requests fetched successfully.');
    }

    public function show(Request $request, TrialRequest $trialRequest)
    {
        abort_unless((int) $trialRequest->member_id === (int) $request->user()->id, 404);

        return $this->success(TrialRequestResource::make(
            $trialRequest->load(['gym', 'branch', 'member', 'assignedTrainer'])
        ));
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
