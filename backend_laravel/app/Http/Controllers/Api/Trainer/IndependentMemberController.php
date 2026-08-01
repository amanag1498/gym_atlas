<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreIndependentMemberInvitationRequest;
use App\Http\Resources\IndependentTrainerMemberInvitationResource;
use App\Http\Resources\IndependentTrainerMemberRelationshipResource;
use App\Models\IndependentTrainerMemberInvitation;
use App\Models\IndependentTrainerMemberRelationship;
use App\Services\Trainer\IndependentTrainerMemberService;
use Illuminate\Http\Request;

class IndependentMemberController extends Controller
{
    public function __construct(private readonly IndependentTrainerMemberService $service) {}

    public function context(Request $request)
    {
        $trainer = $request->user()->loadMissing('managedTrainerProfile');
        $eligibility = $this->service->eligibility($trainer);
        $relationships = IndependentTrainerMemberRelationship::query()
            ->with(['trainer.managedTrainerProfile', 'member'])
            ->where('trainer_user_id', $trainer->id)
            ->latest('id')
            ->get();
        $invitations = IndependentTrainerMemberInvitation::query()
            ->with(['trainer.managedTrainerProfile'])
            ->where('trainer_user_id', $trainer->id)
            ->latest('id')
            ->get();

        return $this->success([
            ...$eligibility,
            'relationships' => IndependentTrainerMemberRelationshipResource::collection($relationships),
            'invitations' => IndependentTrainerMemberInvitationResource::collection($invitations),
        ], 'Independent coaching context fetched successfully.');
    }

    public function invitations(Request $request)
    {
        $this->service->assertEligibleTrainer($request->user()->loadMissing('managedTrainerProfile'));
        $paginator = IndependentTrainerMemberInvitation::query()
            ->with(['trainer.managedTrainerProfile'])
            ->where('trainer_user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            IndependentTrainerMemberInvitationResource::collection($paginator->getCollection()),
            'Independent member invitations fetched successfully.',
        );
    }

    public function store(StoreIndependentMemberInvitationRequest $request)
    {
        $invitation = $this->service->invite($request->user()->loadMissing('managedTrainerProfile'), $request->validated());

        return $this->success(
            IndependentTrainerMemberInvitationResource::make($invitation),
            $invitation->invited_user_id ? 'Invitation sent to the member app for approval.' : 'Enrollment approval email sent.',
            202,
        );
    }

    public function cancel(Request $request, IndependentTrainerMemberInvitation $invitation)
    {
        return $this->success(
            IndependentTrainerMemberInvitationResource::make(
                $this->service->cancelInvitation($request->user(), $invitation),
            ),
            'Independent coaching invitation cancelled.',
        );
    }

    public function members(Request $request)
    {
        $this->service->assertEligibleTrainer($request->user()->loadMissing('managedTrainerProfile'));
        $paginator = IndependentTrainerMemberRelationship::query()
            ->with(['trainer.managedTrainerProfile', 'member'])
            ->where('trainer_user_id', $request->user()->id)
            ->where('status', $request->string('status', 'active')->toString())
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            IndependentTrainerMemberRelationshipResource::collection($paginator->getCollection()),
            'Independent coaching members fetched successfully.',
        );
    }

    public function revoke(Request $request, IndependentTrainerMemberRelationship $relationship)
    {
        $payload = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $this->success(
            IndependentTrainerMemberRelationshipResource::make(
                $this->service->revoke($request->user(), $relationship->loadMissing(['trainer', 'member']), $payload['reason'] ?? null),
            ),
            'Independent coaching relationship revoked.',
        );
    }
}
