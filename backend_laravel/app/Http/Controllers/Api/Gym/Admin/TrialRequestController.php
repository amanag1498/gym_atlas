<?php

namespace App\Http\Controllers\Api\Gym\Admin;

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

        return $this->paginated($paginator, TrialRequestResource::collection($paginator->getCollection()), 'Trial requests fetched successfully.');
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

    public function accept(Request $request, TrialRequest $trialRequest)
    {
        $updated = $this->trialRequestService->accept($request->user(), $trialRequest, $request->input('notes'), $request);

        return $this->success(TrialRequestResource::make($updated), 'Trial request accepted successfully.');
    }

    public function reject(Request $request, TrialRequest $trialRequest)
    {
        $updated = $this->trialRequestService->reject($request->user(), $trialRequest, $request->input('notes'), $request);

        return $this->success(TrialRequestResource::make($updated), 'Trial request rejected successfully.');
    }

    public function complete(Request $request, TrialRequest $trialRequest)
    {
        $updated = $this->trialRequestService->complete($request->user(), $trialRequest, $request->input('notes'), $request);

        return $this->success(TrialRequestResource::make($updated), 'Trial request marked completed successfully.');
    }

    public function assignTrainer(Request $request, TrialRequest $trialRequest)
    {
        $validated = $request->validate([
            'assigned_trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->trialRequestService->assignTrainer(
            $request->user(),
            $trialRequest,
            $validated['assigned_trainer_id'] ?? null,
            $validated['notes'] ?? null,
            $request,
        );

        return $this->success(TrialRequestResource::make($updated), 'Trainer assignment updated successfully.');
    }

    public function convert(Request $request, TrialRequest $trialRequest)
    {
        $validated = $request->validate([
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assigned_trainer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->trialRequestService->convert($request->user(), $trialRequest, $validated, $request);

        return $this->success([
            'trial_request' => TrialRequestResource::make($result['trial_request']),
            'member' => [
                'id' => $result['member']->id,
                'name' => $result['member']->name,
                'email' => $result['member']->email,
            ],
        ], 'Trial request converted successfully.');
    }
}
