<?php

namespace App\Services\Members;

use App\Mail\MemberEnrollmentInvitationMail;
use App\Models\Gym;
use App\Models\MemberEmailInvitation;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\BillingAccessService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\MembershipEnrollmentService;
use App\Services\Notification\ReminderService;
use App\Services\Users\ManagedUserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MemberEmailInvitationService
{
    public function __construct(
        private readonly ManagedUserService $managedUserService,
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly MemberMembershipLifecycleService $membershipLifecycleService,
        private readonly BillingAccessService $billingAccessService,
        private readonly ReminderService $reminderService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function invite(User $actor, Gym $gym, array $payload): MemberEmailInvitation
    {
        $email = strtolower(trim((string) $payload['email']));
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['This email already belongs to an app account. Select that account so it can approve in the app.']]);
        }

        MemberEmailInvitation::query()->where('gym_id', $gym->id)->where('invited_email', $email)->where('status', 'pending')->update(['status' => 'superseded']);
        $invitation = MemberEmailInvitation::query()->create([
            'token' => (string) Str::uuid(), 'gym_id' => $gym->id, 'branch_id' => $payload['branch_id'] ?? null,
            'assigned_trainer_user_id' => $payload['assigned_trainer_user_id'] ?? null, 'invited_by_user_id' => $actor->id,
            'invited_name' => $payload['name'], 'invited_email' => $email, 'status' => 'pending', 'payload' => $payload,
            'expires_at' => now()->addDays(7),
        ]);
        $invitation->load('gym');
        $reviewUrl = URL::temporarySignedRoute('member-email-invitations.review', $invitation->expires_at, ['invitation' => $invitation->id, 'token' => $invitation->token]);
        Mail::to($invitation->invited_email)->send(new MemberEnrollmentInvitationMail($invitation, $reviewUrl));

        return $invitation;
    }

    public function accept(MemberEmailInvitation $invitation): MemberEmailInvitation
    {
        $this->assertActionable($invitation);
        return DB::transaction(function () use ($invitation): MemberEmailInvitation {
            $payload = $invitation->payload;
            $payload['email'] = $invitation->invited_email;
            $payload['name'] = $invitation->invited_name;
            $user = $this->managedUserService->upsertMember(null, $invitation->gym, $payload);
            if (!empty($payload['membership_plan_id'])) {
                $plan = MembershipPlan::query()->findOrFail($payload['membership_plan_id']);
                $this->billingAccessService->assertPlanBelongsToScope($plan, $invitation->gym_id, (int) $payload['branch_id']);
                ['membership' => $membership] = $this->membershipEnrollmentService->enroll($plan, $invitation->invitedBy ?: $user, [...$payload, 'gym_id' => $invitation->gym_id, 'branch_id' => $payload['branch_id'], 'member_id' => $user->id, 'start_date' => $payload['start_date'] ?? now()->toDateString(), 'due_date' => $payload['due_date'] ?? ($payload['start_date'] ?? now()->toDateString())]);
                $this->membershipLifecycleService->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));
                $this->reminderService->syncMembershipReminders($membership->fresh(['membershipPlan']));
            }
            $invitation->forceFill(['status' => 'accepted', 'responded_at' => now()])->save();
            return $invitation;
        });
    }

    public function reject(MemberEmailInvitation $invitation): MemberEmailInvitation
    {
        $this->assertActionable($invitation);
        $invitation->forceFill(['status' => 'rejected', 'responded_at' => now()])->save();
        return $invitation;
    }

    private function assertActionable(MemberEmailInvitation $invitation): void
    {
        if ($invitation->status !== 'pending' || $invitation->expires_at->isPast()) throw ValidationException::withMessages(['invitation' => ['This enrollment invitation is no longer active.']]);
    }
}
