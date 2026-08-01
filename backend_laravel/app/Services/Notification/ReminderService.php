<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\ReminderType;
use App\Models\AttendanceLog;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\ScheduledReminder;
use App\Services\Members\GymMemberAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReminderService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly TransactionalEmailService $transactionalEmailService,
        private readonly GymMemberAccessService $gymMemberAccessService,
    ) {}

    public function syncMembershipReminders(MemberMembership $membership): void
    {
        $membership->loadMissing('member');

        if (in_array($membership->status, ['cancelled', 'frozen'], true)) {
            ScheduledReminder::query()
                ->where('member_membership_id', $membership->id)
                ->update([
                    'status' => 'cancelled',
                ]);

            return;
        }

        $this->upsertReminder(
            $membership,
            ReminderType::MembershipExpiry->value,
            Carbon::parse($membership->expiry_date)->subDays(3),
            'Membership Expiry Reminder',
            'Your membership is expiring soon.'
        );

        if (in_array($membership->payment_status, [
            PaymentStatus::Unpaid->value,
            PaymentStatus::Partial->value,
            PaymentStatus::Overdue->value,
        ], true) && $membership->due_date) {
            $type = $membership->custom_fee_enabled
                ? ReminderType::CustomDue->value
                : ReminderType::PaymentDue->value;

            $this->upsertReminder(
                $membership,
                $type,
                Carbon::parse($membership->due_date)->subDay(),
                'Payment Due Reminder',
                'Your membership payment is due soon.'
            );
        } else {
            ScheduledReminder::query()
                ->where('member_membership_id', $membership->id)
                ->whereIn('type', [
                    ReminderType::PaymentDue->value,
                    ReminderType::CustomDue->value,
                ])
                ->update([
                    'status' => 'cancelled',
                ]);
        }
    }

    public function scheduleAttendanceInactivityReminders(?int $gymId = null, ?int $branchId = null): void
    {
        $profilesQuery = MemberProfile::query()
            ->with(['user:id,name,email', 'gym:id,name', 'branch:id,name'])
            ->whereNotNull('gym_id')
            ->when($gymId, fn (Builder $query) => $query->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('membership_status', '!=', 'frozen');
        $profiles = $this->gymMemberAccessService->scopeAccessibleProfiles($profilesQuery)->get();

        $lastAttendanceByMember = AttendanceLog::query()
            ->select('member_id', DB::raw('MAX(checked_in_at) as last_checked_in_at'))
            ->when($gymId, fn (Builder $query) => $query->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->groupBy('member_id')
            ->pluck('last_checked_in_at', 'member_id');

        foreach ($profiles as $profile) {
            $lastAttendanceAt = $lastAttendanceByMember->get($profile->user_id);

            if (! $lastAttendanceAt || Carbon::parse($lastAttendanceAt)->lt(now()->subDays(7))) {
                $gymName = $profile->gym?->name ?? config('app.name');
                ScheduledReminder::query()->firstOrCreate([
                    'user_id' => $profile->user_id,
                    'gym_id' => $profile->gym_id,
                    'branch_id' => $profile->branch_id,
                    'member_membership_id' => null,
                    'type' => ReminderType::AttendanceInactivity->value,
                    'status' => 'pending',
                ], [
                    'title' => 'We miss you at '.$gymName,
                    'body' => 'It has been a while since your last check-in at '.$gymName.'. We are ready when you are.',
                    'scheduled_for' => now(),
                    'payload' => [
                        'last_attendance_at' => $lastAttendanceAt ? Carbon::parse($lastAttendanceAt)->toIso8601String() : null,
                        'member_name' => $profile->user?->name,
                        'gym_name' => $gymName,
                        'branch_name' => $profile->branch?->name,
                        'source' => 'gym',
                    ],
                ]);
            }
        }
    }

    public function runDueReminders(?string $type = null, ?int $gymId = null, ?int $branchId = null): Collection
    {
        $this->scheduleAttendanceInactivityReminders($gymId, $branchId);

        $query = ScheduledReminder::query()
            ->with(['user', 'gym:id,name', 'branch:id,name', 'membership.membershipPlan:id,name'])
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->when($type, fn (Builder $builder) => $builder->where('type', $type))
            ->when($gymId, fn (Builder $builder) => $builder->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $builder) => $builder->where('branch_id', $branchId));

        $processed = collect();

        foreach ($query->get() as $reminder) {
            if (! $this->isStillDeliverable($reminder)) {
                $reminder->forceFill(['status' => 'cancelled'])->save();

                continue;
            }

            $notificationType = match ($reminder->type) {
                ReminderType::MembershipExpiry->value => NotificationType::MembershipExpiry->value,
                ReminderType::PaymentDue->value => NotificationType::PaymentDue->value,
                ReminderType::CustomDue->value => NotificationType::CustomDue->value,
                ReminderType::AttendanceInactivity->value => NotificationType::AttendanceInactivity->value,
                default => NotificationType::WorkoutReminder->value,
            };

            $notification = $this->notificationService->create(
                user: $reminder->user,
                type: $notificationType,
                title: $reminder->title,
                body: $reminder->body,
                gymId: $reminder->gym_id,
                branchId: $reminder->branch_id,
                membershipId: $reminder->member_membership_id,
                data: $reminder->payload,
                scheduledFor: $reminder->scheduled_for,
            );

            $reminder->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();

            $this->transactionalEmailService->send(
                $reminder->user,
                $reminder->title,
                $reminder->body,
                array_filter([
                    $reminder->payload['due_amount'] ?? null ? 'Due amount: '.number_format((float) $reminder->payload['due_amount'], 2) : null,
                    $reminder->payload['due_date'] ?? null ? 'Due date: '.$reminder->payload['due_date'] : null,
                    $reminder->payload['expiry_date'] ?? null ? 'Expiry date: '.$reminder->payload['expiry_date'] : null,
                ]),
                $reminder->gym_id,
                'scheduled_reminder',
                [
                    'branch_id' => $reminder->branch_id,
                    'category_label' => str_replace('_', ' ', $reminder->type),
                ],
            );

            $processed->push([
                'reminder' => $reminder,
                'notification' => $notification,
            ]);
        }

        return $processed;
    }

    private function upsertReminder(
        MemberMembership $membership,
        string $type,
        Carbon $scheduledFor,
        string $title,
        string $body,
    ): void {
        $membership->loadMissing(['member:id,name,email', 'gym:id,name', 'branch:id,name', 'membershipPlan:id,name']);
        $gymName = $membership->gym?->name ?? config('app.name');
        $planName = $membership->membershipPlan?->name;

        ScheduledReminder::query()->updateOrCreate([
            'user_id' => $membership->member_id,
            'gym_id' => $membership->gym_id,
            'branch_id' => $membership->branch_id,
            'member_membership_id' => $membership->id,
            'type' => $type,
        ], [
            'title' => $title.' — '.$gymName,
            'body' => $body.' This reminder is from '.$gymName.'.',
            'payload' => [
                'membership_id' => $membership->id,
                'member_name' => $membership->member?->name,
                'gym_name' => $gymName,
                'branch_name' => $membership->branch?->name,
                'plan_name' => $planName,
                'membership_status' => $membership->status,
                'due_amount' => (float) $membership->due_amount,
                'expiry_date' => $membership->expiry_date?->toDateString(),
                'due_date' => $membership->due_date?->toDateString(),
                'source' => 'gym',
            ],
            'scheduled_for' => $scheduledFor,
            'status' => 'pending',
            'sent_at' => null,
        ]);
    }

    private function isStillDeliverable(ScheduledReminder $reminder): bool
    {
        if (! $reminder->user || ! $reminder->gym_id) {
            return false;
        }

        if ($reminder->member_membership_id) {
            $membership = $reminder->membership;

            return $membership !== null
                && (int) $membership->member_id === (int) $reminder->user_id
                && (int) $membership->gym_id === (int) $reminder->gym_id
                && $membership->status === 'active'
                && $membership->start_date?->startOfDay()->lte(today())
                && $membership->expiry_date?->endOfDay()->gte(today());
        }

        $profileQuery = MemberProfile::query()
            ->where('user_id', $reminder->user_id)
            ->where('gym_id', $reminder->gym_id)
            ->when($reminder->branch_id, fn (Builder $query) => $query->where('branch_id', $reminder->branch_id));

        return $this->gymMemberAccessService->scopeAccessibleProfiles($profileQuery)->exists();
    }
}
