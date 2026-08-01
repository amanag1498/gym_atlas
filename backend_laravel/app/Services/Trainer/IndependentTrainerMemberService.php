<?php

namespace App\Services\Trainer;

use App\Enums\RoleName;
use App\Mail\IndependentTrainerMemberInvitationMail;
use App\Models\ActivityLog;
use App\Models\IndependentTrainerMemberInvitation;
use App\Models\IndependentTrainerMemberRelationship;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TransactionalEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IndependentTrainerMemberService
{
    private const DEFAULT_SHARING_PERMISSIONS = ['profile', 'workouts', 'diets', 'progress', 'chat'];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TransactionalEmailService $transactionalEmailService,
    ) {}

    public function eligibility(User $trainer): array
    {
        $profile = TrainerProfile::query()->where('user_id', $trainer->id)->first();
        $isIndependent = $profile !== null && $profile->gym_id === null && $profile->branch_id === null;
        $eligible = $trainer->is_active
            && $isIndependent
            && $profile->is_active
            && $profile->status === 'active'
            && $profile->verification_status === 'verified';

        return [
            'eligible' => $eligible,
            'is_independent' => $isIndependent,
            'verification_status' => $profile?->verification_status ?? 'missing',
            'blocking_reason' => $eligible ? null : $this->blockingReason($trainer, $profile),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function invite(User $trainer, array $payload): IndependentTrainerMemberInvitation
    {
        $this->assertEligibleTrainer($trainer);

        $email = strtolower(trim((string) $payload['email']));
        $existingMember = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existingMember !== null && ! $existingMember->hasRole(RoleName::Member->value)) {
            throw ValidationException::withMessages([
                'email' => ['This email belongs to a non-member account and cannot be invited as a coaching member.'],
            ]);
        }

        if ($existingMember?->id === $trainer->id) {
            throw ValidationException::withMessages(['email' => ['A trainer cannot invite their own account.']]);
        }

        if ($existingMember === null && ! $this->transactionalEmailService->isEnabled()) {
            throw ValidationException::withMessages([
                'email' => ['Transactional email is currently disabled. Invite an existing Atlas member or contact support.'],
            ]);
        }

        return DB::transaction(function () use ($trainer, $payload, $email, $existingMember): IndependentTrainerMemberInvitation {
            $this->assertEligibleTrainer($trainer, lockForUpdate: true);

            $existingRelationships = IndependentTrainerMemberRelationship::query()
                ->where('trainer_user_id', $trainer->id)
                ->where('invited_email', $email)
                ->lockForUpdate()
                ->get();

            if ($existingRelationships->contains('status', 'active')) {
                throw ValidationException::withMessages(['email' => ['This member already has an active independent coaching relationship with you.']]);
            }

            IndependentTrainerMemberInvitation::query()
                ->where('trainer_user_id', $trainer->id)
                ->where('invited_email', $email)
                ->where('status', 'pending')
                ->update(['status' => 'superseded', 'responded_at' => now()]);

            $pendingRelationshipIds = $existingRelationships->where('status', 'pending')->pluck('id');
            if ($pendingRelationshipIds->isNotEmpty()) {
                IndependentTrainerMemberRelationship::query()
                    ->whereKey($pendingRelationshipIds)
                    ->update(['status' => 'superseded', 'is_current' => null]);
            }

            $sharingPermissions = array_values(array_unique($payload['sharing_permissions'] ?? self::DEFAULT_SHARING_PERMISSIONS));
            $relationship = new IndependentTrainerMemberRelationship([
                'trainer_user_id' => $trainer->id,
                'invited_email' => $email,
                'is_current' => true,
            ]);
            $relationship->fill([
                'member_user_id' => $existingMember?->id,
                'status' => 'pending',
                'sharing_permissions' => $sharingPermissions,
                'accepted_at' => null,
                'declined_at' => null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'revocation_reason' => null,
            ])->save();

            $invitation = IndependentTrainerMemberInvitation::query()->create([
                'relationship_id' => $relationship->id,
                'token' => (string) Str::uuid(),
                'trainer_user_id' => $trainer->id,
                'invited_user_id' => $existingMember?->id,
                'invited_name' => trim((string) $payload['name']),
                'invited_email' => $email,
                'invited_by_user_id' => $trainer->id,
                'status' => 'pending',
                'payload' => [
                    'message' => $payload['message'] ?? null,
                    'sharing_permissions' => $sharingPermissions,
                ],
                'expires_at' => now()->addDays(7),
            ]);

            if ($existingMember !== null) {
                $this->notificationService->create(
                    user: $existingMember,
                    type: 'independent_trainer_invitation',
                    title: 'Independent coaching invitation',
                    body: $trainer->name.' wants to coach you independently.',
                    createdByUserId: $trainer->id,
                    data: [
                        'invitation_id' => $invitation->id,
                        'relationship_id' => $relationship->id,
                        'trainer_user_id' => $trainer->id,
                        'trainer_name' => $trainer->name,
                        'status' => 'pending',
                        'source' => 'independent',
                        'accept_endpoint' => '/api/member/independent-trainer-invitations/'.$invitation->id.'/accept',
                        'reject_endpoint' => '/api/member/independent-trainer-invitations/'.$invitation->id.'/reject',
                    ],
                );
            } else {
                $invitation->load('trainer');
                $reviewUrl = URL::temporarySignedRoute(
                    'independent-trainer-member-invitations.review',
                    $invitation->expires_at,
                    ['invitation' => $invitation->id, 'token' => $invitation->token],
                );
                $this->transactionalEmailService->sendMailableTo(
                    $email,
                    new IndependentTrainerMemberInvitationMail($invitation, $reviewUrl),
                    'Review your coaching invitation from '.$trainer->name,
                    null,
                    'independent_trainer_invitation',
                );
            }

            $this->audit('independent_trainer_member.invited', 'create', $trainer, $relationship, [
                'invitation_id' => $invitation->id,
                'approval_channel' => $existingMember ? 'app' : 'email',
                'invited_email' => $email,
            ]);

            return $invitation->fresh(['trainer.managedTrainerProfile', 'relationship']);
        });
    }

    public function accept(IndependentTrainerMemberInvitation $invitation, ?User $member = null): IndependentTrainerMemberRelationship
    {
        return DB::transaction(function () use ($invitation, $member): IndependentTrainerMemberRelationship {
            $invitation = IndependentTrainerMemberInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->assertInvitationActionable($invitation, $member);
            $this->assertEligibleTrainer(
                $invitation->trainer()->with('managedTrainerProfile')->firstOrFail(),
                lockForUpdate: true,
            );

            $member ??= $this->resolveOrCreateEmailMember($invitation);
            $relationship = IndependentTrainerMemberRelationship::query()->lockForUpdate()->findOrFail($invitation->relationship_id);
            $relationship->forceFill([
                'member_user_id' => $member->id,
                'status' => 'active',
                'accepted_at' => now(),
                'declined_at' => null,
                'revoked_at' => null,
                'revoked_by_user_id' => null,
                'revocation_reason' => null,
            ])->save();
            $invitation->forceFill(['status' => 'accepted', 'responded_at' => now(), 'invited_user_id' => $member->id])->save();

            $this->updateMemberInvitationNotification($member, $invitation, 'accepted');
            $this->notifyTrainer($relationship, 'accepted');
            $this->audit('independent_trainer_member.accepted', 'accept', $member, $relationship, ['invitation_id' => $invitation->id]);

            return $relationship->fresh(['trainer.managedTrainerProfile', 'member']);
        });
    }

    public function decline(IndependentTrainerMemberInvitation $invitation, ?User $member = null): IndependentTrainerMemberRelationship
    {
        return DB::transaction(function () use ($invitation, $member): IndependentTrainerMemberRelationship {
            $invitation = IndependentTrainerMemberInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->assertInvitationActionable($invitation, $member);
            $relationship = IndependentTrainerMemberRelationship::query()->lockForUpdate()->findOrFail($invitation->relationship_id);
            $relationship->forceFill(['status' => 'declined', 'is_current' => null, 'declined_at' => now()])->save();
            $invitation->forceFill(['status' => 'declined', 'responded_at' => now()])->save();

            if ($member !== null) {
                $this->updateMemberInvitationNotification($member, $invitation, 'declined');
            }
            $this->notifyTrainer($relationship, 'declined');
            $this->audit('independent_trainer_member.declined', 'decline', $member, $relationship, ['invitation_id' => $invitation->id]);

            return $relationship->fresh(['trainer.managedTrainerProfile', 'member']);
        });
    }

    public function revoke(User $actor, IndependentTrainerMemberRelationship $relationship, ?string $reason = null): IndependentTrainerMemberRelationship
    {
        return DB::transaction(function () use ($actor, $relationship, $reason): IndependentTrainerMemberRelationship {
            $relationship = IndependentTrainerMemberRelationship::query()
                ->with(['trainer', 'member'])
                ->lockForUpdate()
                ->findOrFail($relationship->id);
            abort_unless(in_array($actor->id, [$relationship->trainer_user_id, $relationship->member_user_id], true), 404);

            if ($relationship->status !== 'active') {
                throw ValidationException::withMessages(['relationship' => ['Only an active coaching relationship can be revoked.']]);
            }

            $relationship->forceFill([
                'status' => 'revoked',
                'is_current' => null,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revocation_reason' => $reason,
            ])->save();

            $otherUser = $actor->id === $relationship->trainer_user_id ? $relationship->member : $relationship->trainer;
            if ($otherUser !== null) {
                $this->notificationService->create(
                    user: $otherUser,
                    type: 'independent_coaching_revoked',
                    title: 'Independent coaching connection ended',
                    body: $actor->name.' ended the independent coaching connection.',
                    createdByUserId: $actor->id,
                    data: ['relationship_id' => $relationship->id, 'status' => 'revoked', 'source' => 'independent'],
                );
            }
            $this->audit('independent_trainer_member.revoked', 'revoke', $actor, $relationship, ['reason' => $reason]);

            return $relationship->fresh(['trainer.managedTrainerProfile', 'member']);
        });
    }

    public function cancelInvitation(User $trainer, IndependentTrainerMemberInvitation $invitation): IndependentTrainerMemberInvitation
    {
        return DB::transaction(function () use ($trainer, $invitation): IndependentTrainerMemberInvitation {
            $invitation = IndependentTrainerMemberInvitation::query()
                ->with(['relationship', 'trainer.managedTrainerProfile'])
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            abort_unless((int) $invitation->trainer_user_id === (int) $trainer->id, 404);

            if ($invitation->status !== 'pending') {
                throw ValidationException::withMessages([
                    'invitation' => ['Only a pending independent coaching invitation can be cancelled.'],
                ]);
            }

            $relationship = IndependentTrainerMemberRelationship::query()
                ->lockForUpdate()
                ->findOrFail($invitation->relationship_id);

            $invitation->forceFill([
                'status' => 'cancelled',
                'responded_at' => now(),
            ])->save();
            $relationship->forceFill([
                'status' => 'cancelled',
                'is_current' => null,
            ])->save();

            if ($invitation->invited_user_id !== null) {
                $member = User::query()->find($invitation->invited_user_id);
                if ($member !== null) {
                    $this->updateMemberInvitationNotification($member, $invitation, 'cancelled');
                    $this->notificationService->create(
                        user: $member,
                        type: 'independent_coaching_revoked',
                        title: 'Independent coaching invitation withdrawn',
                        body: $trainer->name.' withdrew the pending coaching invitation.',
                        createdByUserId: $trainer->id,
                        data: [
                            'invitation_id' => $invitation->id,
                            'relationship_id' => $relationship->id,
                            'status' => 'cancelled',
                            'source' => 'independent',
                        ],
                    );
                }
            }

            $this->audit('independent_trainer_member.invitation_cancelled', 'cancel', $trainer, $relationship, [
                'invitation_id' => $invitation->id,
                'invited_email' => $invitation->invited_email,
            ]);

            return $invitation->fresh(['trainer.managedTrainerProfile', 'relationship']);
        });
    }

    public function assertEligibleTrainer(User $trainer, bool $lockForUpdate = false): TrainerProfile
    {
        $query = TrainerProfile::query()->where('user_id', $trainer->id);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $profile = $query->first();
        $eligible = $trainer->is_active
            && $profile !== null
            && $profile->gym_id === null
            && $profile->branch_id === null
            && $profile->is_active
            && $profile->status === 'active'
            && $profile->verification_status === 'verified';

        if (! $eligible) {
            throw ValidationException::withMessages([
                'trainer' => [$this->blockingReason($trainer, $profile)],
            ]);
        }

        return $profile;
    }

    private function assertInvitationActionable(IndependentTrainerMemberInvitation $invitation, ?User $member): void
    {
        if ($member !== null) {
            abort_unless((int) $invitation->invited_user_id === (int) $member->id, 404);
        }
        if ($invitation->status !== 'pending' || $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages(['invitation' => ['This independent coaching invitation is no longer active.']]);
        }
    }

    private function resolveOrCreateEmailMember(IndependentTrainerMemberInvitation $invitation): User
    {
        $member = User::query()->whereRaw('LOWER(email) = ?', [$invitation->invited_email])->first();
        if ($member !== null && ! $member->hasRole(RoleName::Member->value)) {
            throw ValidationException::withMessages(['email' => ['This email now belongs to a non-member account.']]);
        }
        if ($member === null) {
            $member = User::query()->create([
                'name' => $invitation->invited_name,
                'email' => $invitation->invited_email,
                'auth_provider' => 'invitation',
                'is_active' => true,
            ]);
            $member->forceFill(['active_role' => RoleName::Member->value, 'email_verified_at' => now()])->save();
        }
        $member->assignRole(RoleName::Member->value);

        return $member;
    }

    private function updateMemberInvitationNotification(User $member, IndependentTrainerMemberInvitation $invitation, string $status): void
    {
        $notification = $member->notifications()
            ->where('type', 'independent_trainer_invitation')
            ->where('data->invitation_id', $invitation->id)
            ->latest('id')
            ->first();
        if ($notification !== null) {
            $notification->forceFill(['data' => [...($notification->data ?? []), 'status' => $status]])->save();
        }
    }

    private function notifyTrainer(IndependentTrainerMemberRelationship $relationship, string $status): void
    {
        $trainer = $relationship->trainer()->first();
        if ($trainer === null) {
            return;
        }
        $this->notificationService->create(
            user: $trainer,
            type: 'independent_coaching_response',
            title: 'Independent coaching invitation '.$status,
            body: $relationship->invited_email.' '.$status.' your coaching invitation.',
            createdByUserId: $relationship->member_user_id,
            data: ['relationship_id' => $relationship->id, 'status' => $status, 'source' => 'independent'],
        );
    }

    private function blockingReason(User $trainer, ?TrainerProfile $profile): string
    {
        if (! $trainer->is_active || $profile === null || ! $profile->is_active || $profile->status !== 'active') {
            return 'Your trainer account must be active before you can invite independent members.';
        }
        if ($profile->gym_id !== null || $profile->branch_id !== null) {
            return 'Independent member enrollment is available only to trainers who are not assigned to a gym.';
        }
        if ($profile->verification_status !== 'verified') {
            return 'Your independent trainer account must be verified before you can invite members.';
        }

        return 'Independent member enrollment is not available for this account.';
    }

    /** @param array<string, mixed> $context */
    private function audit(string $event, string $action, ?User $actor, IndependentTrainerMemberRelationship $relationship, array $context): void
    {
        ActivityLog::query()->create([
            'actor_user_id' => $actor?->id,
            'user_id' => $actor?->id,
            'event' => $event,
            'action' => $action,
            'actor_role' => $actor?->active_role,
            'subject_type' => $relationship->getMorphClass(),
            'subject_id' => $relationship->id,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
