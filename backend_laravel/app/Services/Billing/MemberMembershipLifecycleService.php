<?php

namespace App\Services\Billing;

use App\Enums\MembershipStatus;
use App\Enums\NotificationType;
use App\Enums\ReminderType;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\Payment;
use App\Models\ScheduledReminder;
use App\Models\User;
use App\Services\Member\MemberAppService;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TransactionalEmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberMembershipLifecycleService
{
    public function __construct(
        private readonly MembershipEnrollmentService $membershipEnrollmentService,
        private readonly NotificationService $notificationService,
        private readonly TransactionalEmailService $transactionalEmailService,
        private readonly MemberAppService $memberAppService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{membership: MemberMembership, initial_payment: Payment|null}
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
        if ($membership->status !== MembershipStatus::Active->value) {
            throw ValidationException::withMessages([
                'membership' => ['Only an active membership can be paused.'],
            ]);
        }

        $membership->status = MembershipStatus::Frozen->value;
        $membership->paused_at = now()->startOfDay();
        $membership->save();

        $this->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));
        $this->cancelAttendanceInactivityReminders($membership);
        $this->notifyMemberOfPause($membership);

        return $membership;
    }

    public function reactivate(MemberMembership $membership, ?string $dueDate = null): MemberMembership
    {
        if ($membership->status !== MembershipStatus::Frozen->value) {
            throw ValidationException::withMessages([
                'membership' => ['Only a paused membership can be resumed.'],
            ]);
        }

        $resumedAt = now()->startOfDay();
        $pausedDays = $membership->paused_at
            ? $membership->paused_at->diffInDays($resumedAt)
            : 0;

        $membership->status = MembershipStatus::Active->value;
        $membership->total_paused_days = (int) $membership->total_paused_days + $pausedDays;
        $membership->last_resumed_at = $resumedAt;

        if ($pausedDays > 0) {
            $membership->expiry_date = $membership->expiry_date->copy()->addDays($pausedDays);
            if ($membership->due_date) {
                $membership->due_date = $membership->due_date->copy()->addDays($pausedDays);
            }
        }

        $membership->paused_at = null;

        if ($dueDate !== null) {
            $membership->due_date = $dueDate;
        }

        $membership->save();

        $this->syncMemberProfileFromMembership($membership->fresh(['member.memberProfile']));
        $this->notifyMemberOfResume($membership, $pausedDays);

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
        return DB::transaction(function () use ($membership): MemberMembership {
            $membership = MemberMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $membership->status = MembershipStatus::Cancelled->value;
            $membership->save();

            $member = $membership->member()->first();
            $memberProfile = $member instanceof User
                ? $member->memberProfiles()->where('gym_id', $membership->gym_id)->first()
                : null;
            $previousTrainerId = $memberProfile?->assigned_trainer_user_id;
            $this->syncMemberProfileSummary($member, $membership->gym_id);
            if ($member instanceof User) {
                $this->memberAppService->revokeGymAccess($member, $membership->gym_id);
                $membership->loadMissing(['gym:id,name', 'branch:id,name', 'membershipPlan:id,name']);
                $gymName = $membership->gym?->name ?? config('app.name');
                $this->notificationService->create(
                    user: $member,
                    type: 'membership_cancelled',
                    title: 'Gym membership cancelled',
                    body: 'Your membership at '.$gymName.' has been cancelled. Gym staff and trainer access has ended.',
                    gymId: $membership->gym_id,
                    branchId: $membership->branch_id,
                    membershipId: $membership->id,
                    data: [
                        'membership_id' => $membership->id,
                        'status' => 'cancelled',
                        'gym_name' => $gymName,
                        'branch_name' => $membership->branch?->name,
                        'plan_name' => $membership->membershipPlan?->name,
                    ],
                );
                DB::afterCommit(fn () => $this->transactionalEmailService->send(
                    $member,
                    'Membership cancelled — '.$gymName,
                    'Your membership at '.$gymName.' has been cancelled.',
                    array_filter([
                        $membership->branch ? 'Branch: '.$membership->branch->name : null,
                        $membership->membershipPlan ? 'Plan: '.$membership->membershipPlan->name : null,
                        'Gym staff and gym-assigned trainer access has ended.',
                        'Any separate independent coaching relationship remains unchanged.',
                    ]),
                    $membership->gym_id,
                    'membership_cancellation',
                    ['branch_id' => $membership->branch_id, 'category_label' => 'Membership cancelled'],
                ));
            }
            $previousTrainer = $previousTrainerId ? User::query()->find($previousTrainerId) : null;
            if ($previousTrainer !== null) {
                $this->notificationService->create(
                    user: $previousTrainer,
                    type: 'trainer_assignment_removed',
                    title: 'Member gym access ended',
                    body: ($member?->name ?? 'A member').' no longer has an active membership in this gym.',
                    gymId: $membership->gym_id,
                    branchId: $membership->branch_id,
                    data: [
                        'member_user_id' => $membership->member_id,
                        'membership_id' => $membership->id,
                        'source' => 'membership_cancelled',
                    ],
                );
            }
            $membership->scheduledReminders()
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
            $this->cancelAttendanceInactivityReminders($membership);

            return $membership->fresh();
        });
    }

    public function syncMemberProfileFromMembership(MemberMembership $membership): void
    {
        $member = $membership->member;

        if (! $member instanceof User) {
            $member = $membership->member()->first();
        }

        $this->syncMemberProfileSummary($member, $membership->gym_id);
    }

    public function syncMemberProfileSummary(?User $member, int $gymId, ?string $asOfDate = null): bool
    {
        if (! $member) {
            return false;
        }

        $asOfDate ??= today()->toDateString();

        $profile = $member->memberProfile()
            ->where('gym_id', $gymId)
            ->first();

        if (! $profile instanceof MemberProfile) {
            return false;
        }

        $current = MemberMembership::query()
            ->where('gym_id', $gymId)
            ->where('member_id', $member->id)
            ->where(function ($query) use ($asOfDate): void {
                $query->where('status', MembershipStatus::Frozen->value)
                    ->orWhere(function ($active) use ($asOfDate): void {
                        $active->where('status', MembershipStatus::Active->value)
                            ->whereDate('start_date', '<=', $asOfDate)
                            ->whereDate('expiry_date', '>=', $asOfDate);
                    });
            })
            ->currentFirst()
            ->first();

        if (! $current) {
            $future = MemberMembership::query()
                ->where('gym_id', $gymId)
                ->where('member_id', $member->id)
                ->where('status', MembershipStatus::Active->value)
                ->whereDate('start_date', '>', $asOfDate)
                ->whereDate('expiry_date', '>=', $asOfDate)
                ->orderBy('start_date')
                ->orderBy('id')
                ->first();

            if ($future) {
                $changed = $profile->status !== 'inactive'
                    || $profile->is_active
                    || $profile->membership_status !== 'inactive'
                    || $profile->membership_expires_on?->toDateString() !== $future->expiry_date?->toDateString()
                    || (int) $profile->branch_id !== (int) $future->branch_id;
                $profile->forceFill([
                    'branch_id' => $future->branch_id,
                    'status' => 'inactive',
                    'is_active' => false,
                    'membership_status' => 'inactive',
                    'membership_expires_on' => $future->expiry_date,
                ])->save();

                return $changed;
            }

            $latest = MemberMembership::query()
                ->where('gym_id', $gymId)
                ->where('member_id', $member->id)
                ->currentFirst()
                ->first();
            $membershipStatus = $latest?->status ?? 'inactive';
            $changed = $profile->status !== 'inactive'
                || $profile->is_active
                || $profile->membership_status !== $membershipStatus
                || $profile->membership_expires_on?->toDateString() !== $latest?->expiry_date?->toDateString()
                || $profile->assigned_trainer_user_id !== null
                || $profile->assigned_trainer_id !== null;
            $profile->forceFill([
                'status' => 'inactive',
                'is_active' => false,
                'membership_status' => $membershipStatus,
                'membership_expires_on' => $latest?->expiry_date,
                'assigned_trainer_user_id' => null,
                'assigned_trainer_id' => null,
            ])->save();

            return $changed;
        }

        $isOperational = $current->status === MembershipStatus::Frozen->value
            || $current->status === MembershipStatus::Active->value;
        $changed = $profile->status !== ($isOperational ? 'active' : 'inactive')
            || $profile->is_active !== $isOperational
            || $profile->membership_status !== $current->status
            || $profile->membership_expires_on?->toDateString() !== $current->expiry_date?->toDateString()
            || (int) $profile->branch_id !== (int) $current->branch_id;
        $profile->forceFill([
            'branch_id' => $current->branch_id,
            'status' => $isOperational ? 'active' : 'inactive',
            'is_active' => $isOperational,
            'membership_status' => $current->status,
            'membership_expires_on' => $current->expiry_date,
            ...($isOperational ? [] : [
                'assigned_trainer_user_id' => null,
                'assigned_trainer_id' => null,
            ]),
        ])->save();

        return $changed;
    }

    private function cancelAttendanceInactivityReminders(MemberMembership $membership): void
    {
        ScheduledReminder::query()
            ->where('user_id', $membership->member_id)
            ->where('gym_id', $membership->gym_id)
            ->where('branch_id', $membership->branch_id)
            ->whereNull('member_membership_id')
            ->where('type', ReminderType::AttendanceInactivity->value)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    private function notifyMemberOfPause(MemberMembership $membership): void
    {
        $membership->loadMissing(['member', 'membershipPlan', 'gym', 'branch']);
        $member = $membership->member;

        if (! $member instanceof User) {
            return;
        }

        $gymName = $membership->gym?->name ?? config('app.name');
        $planName = $membership->membershipPlan?->name;

        DB::afterCommit(function () use ($membership, $member, $gymName, $planName): void {
            $this->notificationService->create(
                user: $member,
                type: NotificationType::MembershipPaused->value,
                title: 'Membership paused',
                body: 'Your membership at '.$gymName.' is paused. Its expiry and due dates will extend when it is resumed.',
                gymId: $membership->gym_id,
                branchId: $membership->branch_id,
                membershipId: $membership->id,
                data: ['paused_at' => $membership->paused_at?->toDateString()],
            );

            $this->transactionalEmailService->send(
                $member,
                'Membership paused — '.$gymName,
                'Your membership at '.$gymName.' has been paused.',
                array_filter([
                    $planName ? 'Plan: '.$planName : null,
                    $membership->paused_at ? 'Paused from: '.$membership->paused_at->format('d M Y') : null,
                    'Your expiry and due dates will extend by the paused days when the membership is resumed.',
                ]),
                $membership->gym_id,
                'membership_pause',
                ['branch_id' => $membership->branch_id, 'category_label' => 'Membership update'],
            );
        });
    }

    private function notifyMemberOfResume(MemberMembership $membership, int $pausedDays): void
    {
        $membership->loadMissing(['member', 'membershipPlan', 'gym', 'branch']);
        $member = $membership->member;

        if (! $member instanceof User) {
            return;
        }

        $gymName = $membership->gym?->name ?? config('app.name');
        $planName = $membership->membershipPlan?->name;

        DB::afterCommit(function () use ($membership, $member, $gymName, $planName, $pausedDays): void {
            $this->notificationService->create(
                user: $member,
                type: NotificationType::MembershipResumed->value,
                title: 'Membership resumed',
                body: 'Your membership at '.$gymName.' is active again. Its dates were extended by '.$pausedDays.' paused day'.($pausedDays === 1 ? '' : 's').'.',
                gymId: $membership->gym_id,
                branchId: $membership->branch_id,
                membershipId: $membership->id,
                data: [
                    'paused_days' => $pausedDays,
                    'expiry_date' => $membership->expiry_date?->toDateString(),
                    'due_date' => $membership->due_date?->toDateString(),
                ],
            );

            $this->transactionalEmailService->send(
                $member,
                'Membership resumed — '.$gymName,
                'Your membership at '.$gymName.' is active again.',
                array_filter([
                    $planName ? 'Plan: '.$planName : null,
                    'Paused days added: '.$pausedDays,
                    $membership->expiry_date ? 'New expiry date: '.$membership->expiry_date->format('d M Y') : null,
                    $membership->due_date ? 'New due date: '.$membership->due_date->format('d M Y') : null,
                ]),
                $membership->gym_id,
                'membership_resume',
                ['branch_id' => $membership->branch_id, 'category_label' => 'Membership update'],
            );
        });
    }
}
