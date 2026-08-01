<?php

namespace App\Services\Members;

use App\Models\Gym;
use App\Models\MemberGymInvitation;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\BillingAccessService;
use App\Services\Billing\CustomFeeAuditService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\MembershipEnrollmentService;
use App\Services\Notification\NotificationService;
use App\Services\Notification\ReminderService;
use App\Services\Users\ManagedUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberGymInvitationService
{
    public function __construct(
        private readonly ManagedUserService $managedUserService,
        private readonly NotificationService $notificationService,
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly BillingAccessService $billingAccessService,
        private readonly CustomFeeAuditService $customFeeAuditService,
        private readonly ReminderService $reminderService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function invite(User $actor, User $member, Gym $gym, array $payload): MemberGymInvitation
    {
        return DB::transaction(function () use ($actor, $member, $gym, $payload): MemberGymInvitation {
            // Serialize invitations for the same identity so concurrent admin
            // requests cannot create two pending rows for one gym/member pair.
            User::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            $hasCurrentGymAccess = MemberProfile::query()
                ->where('user_id', $member->id)
                ->where('gym_id', $gym->id)
                ->where('is_active', true)
                ->whereIn('membership_status', ['active', 'frozen'])
                ->where(function ($query): void {
                    $query->where('membership_status', 'frozen')
                        ->orWhereNull('membership_expires_on')
                        ->orWhereDate('membership_expires_on', '>=', today());
                })
                ->exists()
            || MemberMembership::query()
                ->where('member_id', $member->id)
                ->where('gym_id', $gym->id)
                ->where(function ($query): void {
                    $query->where('status', 'frozen')
                        ->orWhere(function ($active): void {
                            $active->where('status', 'active')
                                ->whereDate('start_date', '<=', today())
                                ->whereDate('expiry_date', '>=', today());
                        });
                })
                ->exists();
            if ($hasCurrentGymAccess) {
                throw ValidationException::withMessages([
                    'existing_user_id' => ['This user is already a member of this gym.'],
                ]);
            }

            $invitation = MemberGymInvitation::query()
                ->where('invited_user_id', $member->id)
                ->where('gym_id', $gym->id)
                ->where('status', 'pending')
                ->first();

            if (! $invitation) {
                $invitation = new MemberGymInvitation([
                    'gym_id' => $gym->id,
                    'invited_user_id' => $member->id,
                    'invited_email' => $member->email,
                    'status' => 'pending',
                ]);
            }

            $invitation->fill([
                'branch_id' => $payload['branch_id'] ?? null,
                'assigned_trainer_user_id' => $payload['assigned_trainer_user_id'] ?? null,
                'invited_by_user_id' => $actor->id,
                'payload' => $payload,
            ])->save();

            $this->syncInvitationNotification($member, $invitation, $gym, $actor, $payload);

            return $invitation->fresh(['gym', 'branch', 'assignedTrainer', 'invitedBy']);
        });
    }

    public function accept(User $member, MemberGymInvitation $invitation): MemberGymInvitation
    {
        return DB::transaction(function () use ($member, $invitation): MemberGymInvitation {
            $invitation = MemberGymInvitation::query()
                ->with(['gym', 'branch', 'assignedTrainer', 'invitedBy'])
                ->lockForUpdate()
                ->findOrFail($invitation->id);
            $this->assertActionable($member, $invitation);
            $this->assertGymOperational($invitation);
            $payload = $invitation->payload ?? [];
            $payload['name'] = $member->name;
            $payload['email'] = $member->email;

            $this->managedUserService->upsertMember(
                $member,
                $invitation->gym,
                $payload,
                notifyMemberOfTrainerAssignment: false,
            );
            $this->enrollMemberIfRequested($member, $invitation, $payload);

            $invitation->forceFill([
                'status' => 'accepted',
                'responded_at' => now(),
            ])->save();

            $this->completeInvitationNotification($member, $invitation, 'accepted');

            return $invitation->fresh(['gym', 'branch', 'assignedTrainer', 'invitedBy']);
        });
    }

    public function reject(User $member, MemberGymInvitation $invitation): MemberGymInvitation
    {
        return DB::transaction(function () use ($member, $invitation): MemberGymInvitation {
            $invitation = MemberGymInvitation::query()
                ->with(['gym', 'branch', 'assignedTrainer', 'invitedBy'])
                ->lockForUpdate()
                ->findOrFail($invitation->id);
            $this->assertActionable($member, $invitation);

            $invitation->forceFill([
                'status' => 'rejected',
                'responded_at' => now(),
            ])->save();

            $this->completeInvitationNotification($member, $invitation, 'rejected');

            return $invitation->fresh(['gym', 'branch', 'assignedTrainer', 'invitedBy']);
        });
    }

    private function assertActionable(User $member, MemberGymInvitation $invitation): void
    {
        abort_unless((int) $invitation->invited_user_id === (int) $member->id, 404);

        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has already been '.$invitation->status.'.'],
            ]);
        }
    }

    private function assertGymOperational(MemberGymInvitation $invitation): void
    {
        if (! $invitation->gym->is_active
            || $invitation->gym->status !== 'active'
            || ! $invitation->gym->operational_access_enabled) {
            throw ValidationException::withMessages([
                'invitation' => ['This gym is not currently operational, so the invitation cannot be accepted.'],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function syncInvitationNotification(
        User $member,
        MemberGymInvitation $invitation,
        Gym $gym,
        User $actor,
        array $payload,
    ): void {
        $notifications = $member->notifications()
            ->where('type', 'gym_member_invitation')
            ->where('data->invitation_id', $invitation->id)
            ->latest('id')
            ->get();
        $notification = $notifications->first();
        $data = [
            'invitation_id' => $invitation->id,
            'gym_id' => $gym->id,
            'gym_name' => $gym->name,
            'branch_id' => $payload['branch_id'] ?? null,
            'trainer_user_id' => $payload['assigned_trainer_user_id'] ?? null,
            'status' => 'pending',
            'accept_endpoint' => '/api/member/gym-invitations/'.$invitation->id.'/accept',
            'reject_endpoint' => '/api/member/gym-invitations/'.$invitation->id.'/reject',
        ];

        if ($notification === null) {
            $this->notificationService->create(
                user: $member,
                type: 'gym_member_invitation',
                title: 'Gym membership invitation',
                body: $gym->name.' wants to add you as a member.',
                gymId: $gym->id,
                branchId: $payload['branch_id'] ?? null,
                createdByUserId: $actor->id,
                data: $data,
            );

            return;
        }

        $notification->forceFill([
            'branch_id' => $payload['branch_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'title' => 'Gym membership invitation',
            'message' => $gym->name.' wants to add you as a member.',
            'body' => $gym->name.' wants to add you as a member.',
            'data' => [...($notification->data ?? []), ...$data],
            'read_at' => null,
        ])->save();

        $notifications->skip(1)->each->delete();
    }

    private function completeInvitationNotification(
        User $member,
        MemberGymInvitation $invitation,
        string $status,
    ): void {
        $invitation->loadMissing(['gym:id,name', 'branch:id,name', 'assignedTrainer:id,name']);
        $notifications = $member->notifications()
            ->where('type', 'gym_member_invitation')
            ->where('data->invitation_id', $invitation->id)
            ->latest('id')
            ->get();
        $notification = $notifications->first();
        $accepted = $status === 'accepted';
        $trainerName = $invitation->assignedTrainer?->name;
        $title = $accepted ? 'Membership activated' : 'Gym invitation declined';
        $body = $accepted
            ? ($trainerName
                ? 'Your membership at '.$invitation->gym->name.' is active. '.$trainerName.' is your assigned gym trainer.'
                : 'Your membership at '.$invitation->gym->name.' is active.')
            : 'You declined the membership invitation from '.$invitation->gym->name.'.';
        $data = [
            'invitation_id' => $invitation->id,
            'gym_id' => $invitation->gym_id,
            'gym_name' => $invitation->gym->name,
            'branch_id' => $invitation->branch_id,
            'branch_name' => $invitation->branch?->name,
            'trainer_user_id' => $invitation->assigned_trainer_user_id,
            'trainer_name' => $trainerName,
            'status' => $status,
            'source' => 'gym',
        ];

        if ($notification === null) {
            $this->notificationService->create(
                user: $member,
                type: 'gym_member_invitation',
                title: $title,
                body: $body,
                gymId: $invitation->gym_id,
                branchId: $invitation->branch_id,
                createdByUserId: $invitation->invited_by_user_id,
                data: $data,
            )?->forceFill(['read_at' => now()])->save();

            return;
        }

        $notification->forceFill([
            'title' => $title,
            'message' => $body,
            'body' => $body,
            'data' => [...($notification->data ?? []), ...$data],
            'read_at' => now(),
        ])->save();

        $notifications->skip(1)->each->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enrollMemberIfRequested(User $member, MemberGymInvitation $invitation, array $payload): ?MemberMembership
    {
        if (empty($payload['membership_plan_id'])) {
            return null;
        }

        $gym = $invitation->gym()->firstOrFail();
        $branchId = (int) ($payload['branch_id'] ?? $invitation->branch_id);
        $plan = MembershipPlan::query()->findOrFail($payload['membership_plan_id']);
        $this->billingAccessService->assertPlanBelongsToScope($plan, $gym->id, $branchId);
        $actor = $invitation->invitedBy ?: $member;

        ['membership' => $membership] = $this->membershipEnrollmentService->enroll(
            $plan,
            $actor,
            [
                ...$payload,
                'gym_id' => $gym->id,
                'branch_id' => $branchId,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => $payload['start_date'] ?? now()->toDateString(),
                'due_date' => $payload['due_date'] ?? ($payload['start_date'] ?? now()->toDateString()),
            ],
        );

        $this->membershipLifecycleService->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        if ($membership->custom_fee_enabled) {
            $this->customFeeAuditService->log(
                $membership,
                $actor,
                [],
                $membership->only([
                    'custom_fee_enabled',
                    'custom_fee_amount',
                    'discount_type',
                    'discount_amount',
                    'custom_joining_fee',
                    'joining_fee_waived',
                    'partial_month_fee',
                    'pt_custom_fee',
                    'final_payable_amount',
                    'due_amount',
                    'due_date',
                ]),
                $membership->custom_fee_reason ?? 'Initial custom fee applied after member invitation acceptance.',
            );
        }

        $this->reminderService->syncMembershipReminders($membership->fresh(['membershipPlan']));

        return $membership;
    }
}
