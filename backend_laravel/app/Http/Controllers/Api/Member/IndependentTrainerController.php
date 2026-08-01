<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\IndependentTrainerMemberInvitationResource;
use App\Http\Resources\IndependentTrainerMemberRelationshipResource;
use App\Models\IndependentTrainerMemberInvitation;
use App\Models\IndependentTrainerMemberRelationship;
use App\Services\Trainer\IndependentTrainerMemberService;
use Illuminate\Http\Request;

class IndependentTrainerController extends Controller
{
    public function __construct(private readonly IndependentTrainerMemberService $service) {}

    public function invitations(Request $request)
    {
        $paginator = IndependentTrainerMemberInvitation::query()
            ->with(['trainer.managedTrainerProfile'])
            ->where('invited_user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            IndependentTrainerMemberInvitationResource::collection($paginator->getCollection()),
            'Independent trainer invitations fetched successfully.',
        );
    }

    public function accept(Request $request, IndependentTrainerMemberInvitation $invitation)
    {
        return $this->success(
            IndependentTrainerMemberRelationshipResource::make($this->service->accept($invitation, $request->user())),
            'Independent coaching invitation accepted.',
        );
    }

    public function reject(Request $request, IndependentTrainerMemberInvitation $invitation)
    {
        return $this->success(
            IndependentTrainerMemberRelationshipResource::make($this->service->decline($invitation, $request->user())),
            'Independent coaching invitation declined.',
        );
    }

    public function trainers(Request $request)
    {
        $paginator = IndependentTrainerMemberRelationship::query()
            ->with(['trainer.managedTrainerProfile', 'member'])
            ->where('member_user_id', $request->user()->id)
            ->where('status', $request->string('status', 'active')->toString())
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            IndependentTrainerMemberRelationshipResource::collection($paginator->getCollection()),
            'Independent trainers fetched successfully.',
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
