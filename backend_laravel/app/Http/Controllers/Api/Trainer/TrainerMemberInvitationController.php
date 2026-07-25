<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trainer\StoreTrainerMemberInvitationRequest;
use App\Models\User;
use App\Services\Members\MemberEmailInvitationService;
use App\Services\Members\MemberGymInvitationService;
use App\Services\Trainer\TrainerScopeService;

class TrainerMemberInvitationController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
        private readonly MemberGymInvitationService $memberGymInvitationService,
        private readonly MemberEmailInvitationService $memberEmailInvitationService,
    ) {}

    public function store(StoreTrainerMemberInvitationRequest $request)
    {
        $profile = $this->trainerScopeService->resolveTrainerProfile($request);
        $payload = [...$request->validated(), 'branch_id' => $profile->branch_id, 'assigned_trainer_user_id' => $profile->user_id, 'membership_status' => 'active', 'is_active' => true];
        $existingUser = User::query()->where('email', strtolower($payload['email']))->first();
        if ($existingUser) {
            if (! $existingUser->hasRole(RoleName::Member->value)) {
                return response()->json(['message' => 'This email belongs to a non-member account and cannot be invited as a member.'], 422);
            }
            $invitation = $this->memberGymInvitationService->invite($request->user(), $existingUser, $profile->gym, $payload);
            return $this->success(['invitation_id' => $invitation->id, 'approval_channel' => 'app'], 'Invitation sent to the member app for approval.', 202);
        }
        $invitation = $this->memberEmailInvitationService->invite($request->user(), $profile->gym, $payload);
        return $this->success(['invitation_id' => $invitation->id, 'approval_channel' => 'email'], 'Enrollment approval email sent.', 202);
    }
}
