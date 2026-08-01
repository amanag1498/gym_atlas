<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Http\Resources\Member\TrainerConnectionResource;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Member\MemberAppService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberTrainerController extends Controller
{
    public function __construct(
        private readonly MemberAppService $memberAppService,
        private readonly NotificationService $notificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function show(Request $request)
    {
        $profile = $this->memberAppService->memberProfileFor($request->user());
        $membership = $this->memberAppService->currentMembershipFor($request->user());
        $userState = $this->memberAppService->userStateFor($request->user(), $profile, $membership);

        if (! $profile || ! $this->memberAppService->hasActiveMembership($membership, $profile)) {
            return $this->success([
                'enabled' => false,
                'user_state' => $userState,
                'assigned_trainer' => null,
                'trainer_chat_placeholder' => [
                    'enabled' => false,
                    'recipient_user_id' => null,
                    'message' => 'Trainer chat is disabled until a gym assigns you a trainer.',
                ],
            ], 'Trainer chat is disabled until a gym assigns you a trainer.');
        }

        return $this->success(
            array_merge(
                TrainerConnectionResource::make($profile)->resolve(),
                [
                    'enabled' => $profile->assigned_trainer_user_id !== null,
                    'user_state' => $userState,
                ],
            ),
            $profile->assigned_trainer_user_id
                ? 'Trainer connection fetched successfully.'
                : 'Trainer chat is disabled until a gym assigns you a trainer.'
        );
    }

    public function destroy(Request $request)
    {
        $member = $request->user();
        $this->memberAppService->assertRequestedGymContextAccessible($member);
        $membership = $this->memberAppService->currentMembershipFor($member);
        $profile = $this->memberAppService->memberProfileFor($member);

        if (! $profile?->gym_id || ! $this->memberAppService->hasActiveMembership($membership, $profile)) {
            throw ValidationException::withMessages([
                'trainer_assignment' => ['An active gym membership is required to change the gym trainer assignment.'],
            ]);
        }
        if ($profile->assigned_trainer_user_id === null) {
            return $this->success([
                'removed' => false,
                'gym_id' => $profile->gym_id,
                'previous_trainer_user_id' => null,
                'assigned_trainer' => null,
                'independent_relationships_unchanged' => true,
            ], 'No gym trainer is currently assigned.');
        }

        [$profile, $previousTrainerId, $oldValues] = DB::transaction(function () use ($membership, $profile): array {
            $operationalMembership = MemberMembership::query()
                ->whereKey($membership->id)
                ->where(function ($query): void {
                    $query->where('status', 'frozen')
                        ->orWhere(function ($active): void {
                            $active->where('status', 'active')
                                ->whereDate('start_date', '<=', today())
                                ->whereDate('expiry_date', '>=', today());
                        });
                })
                ->lockForUpdate()
                ->first();
            if ($operationalMembership === null) {
                throw ValidationException::withMessages([
                    'trainer_assignment' => ['The gym membership is no longer active.'],
                ]);
            }

            $locked = MemberProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $oldValues = $locked->toArray();
            $previousTrainerId = $locked->assigned_trainer_user_id !== null
                ? (int) $locked->assigned_trainer_user_id
                : null;
            $locked->forceFill([
                'assigned_trainer_user_id' => null,
                'assigned_trainer_id' => null,
            ])->save();

            return [$locked->fresh(['gym', 'branch']), $previousTrainerId, $oldValues];
        });

        if ($previousTrainerId === null) {
            return $this->success([
                'removed' => false,
                'gym_id' => $profile->gym_id,
                'previous_trainer_user_id' => null,
                'assigned_trainer' => null,
                'independent_relationships_unchanged' => true,
            ], 'No gym trainer is currently assigned.');
        }

        $this->auditLogService->log(
            event: 'member.gym_trainer.removed',
            action: 'update',
            request: $request,
            subject: $profile,
            gym: $profile->gym,
            branch: $profile->branch,
            oldValues: $oldValues,
            newValues: $profile->toArray(),
            context: ['previous_trainer_user_id' => $previousTrainerId],
        );

        $trainer = User::query()->find($previousTrainerId);
        if ($trainer !== null) {
            $this->notificationService->create(
                user: $trainer,
                type: 'trainer_assignment_removed',
                title: 'Gym trainer assignment ended',
                body: $member->name.' removed the current gym trainer assignment.',
                gymId: $profile->gym_id,
                branchId: $profile->branch_id,
                createdByUserId: $member->id,
                data: [
                    'member_user_id' => $member->id,
                    'previous_trainer_user_id' => $previousTrainerId,
                    'source' => 'gym',
                ],
            );
        }

        return $this->success([
            'removed' => true,
            'gym_id' => $profile->gym_id,
            'previous_trainer_user_id' => $previousTrainerId,
            'assigned_trainer' => null,
            'independent_relationships_unchanged' => true,
        ], 'Gym trainer assignment removed. Your membership and independent coaching relationships are unchanged.');
    }
}
