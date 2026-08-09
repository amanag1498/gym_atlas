<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Gym\BranchResource;
use App\Http\Resources\Member\MemberAppProfileResource;
use App\Http\Resources\Member\MemberGymInvitationResource;
use App\Http\Resources\Member\MemberGymRelationshipResource;
use App\Http\Resources\Member\MemberMembershipViewResource;
use App\Http\Resources\Member\TrainerConnectionResource;
use App\Http\Resources\User\UserResource;
use App\Models\MemberGymInvitation;
use App\Services\Member\MemberAppService;
use Illuminate\Http\Request;

class MemberContextController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->load([
            'branches',
            'gyms',
        ]);
        $profile = $this->memberAppService->memberProfileFor($user);
        $membership = $this->memberAppService->currentMembershipFor($user);
        $userState = $this->memberAppService->userStateFor($user, $profile, $membership);
        $attendanceStatus = $this->memberAppService->attendanceStatusFor($user, $profile);
        $steps = $this->memberAppService->stepSummaryFor($user, $profile);
        $pendingInvitations = MemberGymInvitation::query()
            ->with(['gym', 'branch', 'assignedTrainer'])
            ->where('invited_user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('gym', fn ($gym) => $gym
                ->where('is_active', true)
                ->where('status', 'active')
                ->where('operational_access_enabled', true))
            ->latest('id')
            ->take(5)
            ->get();
        $gymRelationships = $this->memberAppService->activeGymProfilesFor($user);
        $selectedGymId = $this->memberAppService->selectedGymIdFor($user);
        $accessibleBranches = $gymRelationships->pluck('branch')->filter()->unique('id')->values();

        return $this->success([
            'user' => UserResource::make($user),
            'user_state' => $userState,
            'member_profile' => MemberAppProfileResource::make($user),
            'current_membership' => $membership ? MemberMembershipViewResource::make($membership) : null,
            'selected_gym_id' => $selectedGymId,
            'has_multiple_gyms' => $gymRelationships->count() > 1,
            'gym_relationships' => MemberGymRelationshipResource::collection($gymRelationships),
            'trainer_connection' => $profile
                ? TrainerConnectionResource::make($profile)
                : [
                    'assigned_trainer' => null,
                    'trainer_chat_placeholder' => [
                        'enabled' => false,
                        'recipient_user_id' => null,
                        'message' => 'Connect to a gym and get assigned a trainer to enable chat.',
                    ],
                    'assigned_workout_shortcut' => [
                        'enabled' => false,
                        'label' => 'Assigned Workout',
                        'destination' => 'workout',
                    ],
                ],
            'attendance_status' => $attendanceStatus,
            'steps' => $steps,
            'capabilities' => [
                'workout_day_selection' => true,
                'food_catalog' => true,
            ],
            'gym_invitations' => [
                'pending_count' => $pendingInvitations->count(),
                'pending' => MemberGymInvitationResource::collection($pendingInvitations),
            ],
            'branches' => BranchResource::collection($accessibleBranches),
        ]);
    }
}
