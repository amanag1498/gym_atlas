<?php

namespace App\Services\Billing;

use App\Enums\MembershipStatus;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\User;
use Carbon\Carbon;

class MemberMembershipLifecycleService
{
    public function __construct(
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{membership: MemberMembership, initial_payment: \App\Models\Payment|null}
     */
    public function renew(MemberMembership $membership, User $actor, array $input): array
    {
        $plan = $membership->membershipPlan()->firstOrFail();
        $startDate = Carbon::parse((string) $input['start_date']);
        $expiryDate = isset($input['expiry_date']) && $input['expiry_date'] !== null
            ? Carbon::parse((string) $input['expiry_date'])
            : $startDate->copy()->addDays($plan->duration_days);

        $result = $this->membershipEnrollmentService->enroll($plan, $actor, [
            'gym_id' => $membership->gym_id,
            'branch_id' => $membership->branch_id,
            'member_id' => $membership->member_id,
            'membership_plan_id' => $membership->membership_plan_id,
            'start_date' => $startDate->toDateString(),
            'expiry_date' => $expiryDate->toDateString(),
            'due_date' => $input['due_date'] ?? $expiryDate->toDateString(),
            'status' => $input['status'] ?? MembershipStatus::Active->value,
            'amount_paid' => $input['amount_paid'] ?? 0,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => true,
        ]);

        $this->syncMemberProfileFromMembership($result['membership']->fresh(['member.memberProfile']));

        return $result;
    }

    public function freeze(MemberMembership $membership): MemberMembership
    {
        $membership->status = MembershipStatus::Frozen->value;
        $membership->save();

        $this->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        return $membership;
    }

    public function reactivate(MemberMembership $membership, ?string $dueDate = null): MemberMembership
    {
        $membership->status = MembershipStatus::Active->value;

        if ($dueDate !== null) {
            $membership->due_date = $dueDate;
        }

        $membership->save();

        $this->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        return $membership;
    }

    public function extend(MemberMembership $membership, int $extraDays, ?string $dueDate = null): MemberMembership
    {
        $expiry = Carbon::parse($membership->expiry_date)->addDays($extraDays);
        $membership->expiry_date = $expiry->toDateString();

        if ($dueDate !== null) {
            $membership->due_date = $dueDate;
        }

        if ($membership->status === MembershipStatus::Expired->value) {
            $membership->status = MembershipStatus::Active->value;
        }

        $membership->save();

        $this->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));

        return $membership;
    }

    public function cancel(MemberMembership $membership): MemberMembership
    {
        $membership->status = MembershipStatus::Cancelled->value;
        $membership->save();

        $this->syncMemberProfileSummary($membership->member()->first(), $membership->gym_id);

        return $membership;
    }

    public function syncMemberProfileFromMembership(MemberMembership $membership): void
    {
        $member = $membership->member;

        if (! $member instanceof User) {
            $member = $membership->member()->first();
        }

        $this->syncMemberProfileSummary($member, $membership->gym_id);
    }

    public function syncMemberProfileSummary(?User $member, int $gymId): void
    {
        if (! $member) {
            return;
        }

        $profile = $member->memberProfile()
            ->where('gym_id', $gymId)
            ->first();

        if (! $profile instanceof MemberProfile) {
            return;
        }

        $current = MemberMembership::query()
            ->where('gym_id', $gymId)
            ->where('member_id', $member->id)
            ->currentFirst()
            ->first();

        if (! $current) {
            $profile->forceFill([
                'membership_status' => 'inactive',
                'membership_expires_on' => null,
            ])->save();

            return;
        }

        $profile->forceFill([
            'membership_status' => $current->status,
            'membership_expires_on' => $current->expiry_date,
        ])->save();
    }
}
