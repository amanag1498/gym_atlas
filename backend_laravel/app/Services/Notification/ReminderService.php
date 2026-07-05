<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\ReminderType;
use App\Models\AttendanceLog;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\ScheduledReminder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ReminderService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

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
        $profiles = MemberProfile::query()
            ->when($gymId, fn (Builder $query) => $query->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->get();

        $lastAttendanceByMember = AttendanceLog::query()
            ->select('member_id', DB::raw('MAX(checked_in_at) as last_checked_in_at'))
            ->when($gymId, fn (Builder $query) => $query->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->groupBy('member_id')
            ->pluck('last_checked_in_at', 'member_id');

        foreach ($profiles as $profile) {
            $lastAttendanceAt = $lastAttendanceByMember->get($profile->user_id);

            if (! $lastAttendanceAt || Carbon::parse($lastAttendanceAt)->lt(now()->subDays(7))) {
                ScheduledReminder::query()->firstOrCreate([
                    'user_id' => $profile->user_id,
                    'gym_id' => $profile->gym_id,
                    'branch_id' => $profile->branch_id,
                    'member_membership_id' => null,
                    'type' => ReminderType::AttendanceInactivity->value,
                    'status' => 'pending',
                ], [
                    'title' => 'Attendance Inactivity Reminder',
                    'body' => 'We have not seen you at the gym recently.',
                    'scheduled_for' => now(),
                    'payload' => [
                        'last_attendance_at' => $lastAttendanceAt ? Carbon::parse($lastAttendanceAt)->toIso8601String() : null,
                    ],
                ]);
            }
        }
    }

    public function runDueReminders(?string $type = null, ?int $gymId = null, ?int $branchId = null): Collection
    {
        $this->scheduleAttendanceInactivityReminders($gymId, $branchId);

        $query = ScheduledReminder::query()
            ->with('user')
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->when($type, fn (Builder $builder) => $builder->where('type', $type))
            ->when($gymId, fn (Builder $builder) => $builder->where('gym_id', $gymId))
            ->when($branchId, fn (Builder $builder) => $builder->where('branch_id', $branchId));

        $processed = collect();

        foreach ($query->get() as $reminder) {
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
        ScheduledReminder::query()->updateOrCreate([
            'user_id' => $membership->member_id,
            'gym_id' => $membership->gym_id,
            'branch_id' => $membership->branch_id,
            'member_membership_id' => $membership->id,
            'type' => $type,
        ], [
            'title' => $title,
            'body' => $body,
            'payload' => [
                'membership_id' => $membership->id,
                'due_amount' => (float) $membership->due_amount,
                'expiry_date' => $membership->expiry_date?->toDateString(),
                'due_date' => $membership->due_date?->toDateString(),
            ],
            'scheduled_for' => $scheduledFor,
            'status' => 'pending',
            'sent_at' => null,
        ]);
    }
}
