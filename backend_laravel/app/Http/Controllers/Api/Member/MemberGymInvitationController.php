<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Member\MemberGymInvitationResource;
use App\Models\MemberGymInvitation;
use App\Services\Members\MemberGymInvitationService;
use Illuminate\Http\Request;

class MemberGymInvitationController extends Controller
{
    public function __construct(
        private readonly MemberGymInvitationService $memberGymInvitationService,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = MemberGymInvitation::query()
            ->with(['gym', 'branch', 'assignedTrainer'])
            ->where('invited_user_id', $request->user()->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated(
            $paginator,
            MemberGymInvitationResource::collection($paginator->getCollection()),
            'Gym invitations fetched successfully.'
        );
    }

    public function accept(Request $request, MemberGymInvitation $invitation)
    {
        return $this->success(
            MemberGymInvitationResource::make($this->memberGymInvitationService->accept($request->user(), $invitation)),
            'Gym invitation accepted successfully.'
        );
    }

    public function reject(Request $request, MemberGymInvitation $invitation)
    {
        return $this->success(
            MemberGymInvitationResource::make($this->memberGymInvitationService->reject($request->user(), $invitation)),
            'Gym invitation rejected.'
        );
    }
}
