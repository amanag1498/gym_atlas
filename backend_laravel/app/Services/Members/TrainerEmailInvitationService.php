<?php

namespace App\Services\Members;

use App\Enums\RoleName;
use App\Mail\TrainerEnrollmentInvitationMail;
use App\Models\ActivityLog;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\TrainerEmailInvitation;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TransactionalEmailService;
use App\Services\Users\ManagedUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrainerEmailInvitationService
{
    public function __construct(
        private readonly ManagedUserService $managedUserService,
        private readonly NotificationService $notificationService,
        private readonly TransactionalEmailService $transactionalEmailService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function invite(User $actor, Gym $gym, array $payload): TrainerEmailInvitation
    {
        $email = strtolower(trim((string) $payload['email']));
        $existingUser = User::query()->where('email', $email)->first();
        $existingProfile = $existingUser?->managedTrainerProfile;

        if ($existingProfile?->gym_id !== null) {
            $message = (int) $existingProfile->gym_id === (int) $gym->id
                ? 'This trainer is already assigned to this gym.'
                : 'This trainer is already assigned to another gym. Use a dedicated transfer flow instead.';

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        if (! $existingUser && ! $this->transactionalEmailService->isEnabled($gym->id)) {
            throw ValidationException::withMessages([
                'email' => ['Transactional email is disabled for this gym. Enable it in Gym Settings before sending a trainer invitation.'],
            ]);
        }

        TrainerEmailInvitation::query()
            ->where('gym_id', $gym->id)
            ->where('invited_email', $email)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $invitation = TrainerEmailInvitation::query()->create([
            'token' => (string) Str::uuid(),
            'gym_id' => $gym->id,
            'branch_id' => $payload['branch_id'] ?? null,
            'invited_user_id' => $existingUser?->id,
            'invited_by_user_id' => $actor->id,
            'invited_name' => $payload['name'],
            'invited_email' => $email,
            'status' => 'pending',
            'payload' => $payload,
            'expires_at' => now()->addDays(7),
        ]);

        if ($existingUser?->hasRole(RoleName::Trainer->value)) {
            $this->notificationService->create(
                user: $existingUser,
                type: 'trainer_gym_invitation',
                title: 'Gym trainer invitation',
                body: $gym->name.' wants to add you as a trainer.',
                gymId: $gym->id,
                branchId: $payload['branch_id'] ?? null,
                createdByUserId: $actor->id,
                data: [
                    'invitation_id' => $invitation->id,
                    'status' => 'pending',
                ],
            );
        } else {
            $invitation->load(['gym', 'branch', 'invitedBy']);
            $url = URL::temporarySignedRoute(
                'trainer-email-invitations.review',
                $invitation->expires_at,
                [
                    'invitation' => $invitation->id,
                    'token' => $invitation->token,
                ],
            );

            $this->transactionalEmailService->sendMailableTo(
                $email,
                new TrainerEnrollmentInvitationMail($invitation, $url),
                'Review your trainer invitation from '.$invitation->gym->name,
                $gym->id,
                'trainer_enrollment_invitation',
            );
        }

        $this->recordActivity(
            invitation: $invitation,
            actor: $actor,
            event: 'gym.trainer.invitation.created',
            action: 'create',
            oldValues: null,
            newValues: [
                'status' => 'pending',
                'approval_channel' => $invitation->invited_user_id ? 'in_app' : 'email',
            ],
        );

        return $invitation;
    }

    public function respond(TrainerEmailInvitation $invitation, bool $accept): TrainerEmailInvitation
    {
        return DB::transaction(function () use ($invitation, $accept): TrainerEmailInvitation {
            $invitation = TrainerEmailInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($invitation->status !== 'pending' || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'invitation' => ['This trainer invitation is no longer active.'],
                ]);
            }

            $oldValues = ['status' => $invitation->status];
            $trainer = null;

            if ($accept) {
                $gym = Gym::query()->findOrFail($invitation->gym_id);
                if (! $gym->is_active || $gym->status !== 'active' || ! $gym->operational_access_enabled) {
                    throw ValidationException::withMessages([
                        'invitation' => ['This gym is not currently operational, so the invitation cannot be accepted.'],
                    ]);
                }

                $trainer = User::query()
                    ->where('email', $invitation->invited_email)
                    ->first();
                $currentGymId = $trainer?->managedTrainerProfile?->gym_id;

                if ($currentGymId !== null && (int) $currentGymId !== (int) $invitation->gym_id) {
                    throw ValidationException::withMessages([
                        'invitation' => ['You are already assigned to another gym. A transfer is required before accepting this invitation.'],
                    ]);
                }

                $payload = $invitation->payload;
                $payload['name'] = $invitation->invited_name;
                $payload['email'] = $invitation->invited_email;
                $trainer = $this->managedUserService->upsertTrainer(
                    $trainer,
                    $invitation->gym,
                    $payload,
                );

                $invitation->update([
                    'invited_user_id' => $trainer->id,
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);

                DB::afterCommit(fn () => $this->transactionalEmailService->send(
                    $trainer,
                    'Trainer enrollment confirmed — '.$invitation->gym->name,
                    'Your trainer enrollment has been approved.',
                    [],
                    $invitation->gym_id,
                    'trainer_enrollment_confirmation',
                    ['branch_id' => $invitation->branch_id, 'category_label' => 'Trainer enrollment confirmed'],
                ));
            } else {
                $invitation->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                ]);
            }

            $this->updateInvitationNotification($invitation);
            $this->recordActivity(
                invitation: $invitation,
                actor: $trainer ?? $invitation->invitedUser,
                event: $accept
                    ? 'gym.trainer.invitation.accepted'
                    : 'gym.trainer.invitation.rejected',
                action: 'update',
                oldValues: $oldValues,
                newValues: ['status' => $invitation->status],
            );

            return $invitation->fresh();
        });
    }

    public function respondForUser(
        User $user,
        TrainerEmailInvitation $invitation,
        bool $accept,
    ): TrainerEmailInvitation {
        abort_unless((int) $invitation->invited_user_id === (int) $user->id, 404);

        return $this->respond($invitation, $accept);
    }

    private function updateInvitationNotification(TrainerEmailInvitation $invitation): void
    {
        if (! $invitation->invited_user_id) {
            return;
        }

        Notification::query()
            ->where('user_id', $invitation->invited_user_id)
            ->where('type', 'trainer_gym_invitation')
            ->get()
            ->filter(
                fn (Notification $notification): bool => (int) ($notification->data['invitation_id'] ?? 0) === $invitation->id,
            )
            ->each(function (Notification $notification) use ($invitation): void {
                $notification->forceFill([
                    'data' => [
                        ...($notification->data ?? []),
                        'status' => $invitation->status,
                    ],
                    'read_at' => now(),
                ])->save();
            });
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordActivity(
        TrainerEmailInvitation $invitation,
        ?User $actor,
        string $event,
        string $action,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $actor?->id,
            'user_id' => $invitation->invited_user_id,
            'gym_id' => $invitation->gym_id,
            'branch_id' => $invitation->branch_id,
            'event' => $event,
            'action' => $action,
            'actor_role' => $actor?->active_role,
            'subject_type' => $invitation->getMorphClass(),
            'subject_id' => $invitation->id,
            'context' => [
                'invited_email' => $invitation->invited_email,
            ],
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
