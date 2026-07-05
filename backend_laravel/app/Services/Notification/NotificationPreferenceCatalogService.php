<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceCatalogService
{
    public function forUser(User $user): array
    {
        $definitions = $this->definitionsForRole($this->resolveRole($user));
        $existing = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereNull('gym_id')
            ->whereNull('branch_id')
            ->whereIn('notification_type', array_column($definitions, 'notification_type'))
            ->get()
            ->keyBy('notification_type');

        return array_map(function (array $definition) use ($existing): array {
            $preference = $existing->get($definition['notification_type']);

            return [
                ...$definition,
                'gym_id' => null,
                'branch_id' => null,
                'is_enabled' => $preference?->is_enabled ?? true,
            ];
        }, $definitions);
    }

    private function resolveRole(User $user): string
    {
        if ($user->active_role) {
            return $user->active_role;
        }

        foreach ([
            RoleName::PlatformAdmin,
            RoleName::GymOwner,
            RoleName::BranchManager,
            RoleName::GymStaff,
            RoleName::Trainer,
            RoleName::Member,
        ] as $role) {
            if ($user->hasRole($role->value)) {
                return $role->value;
            }
        }

        return RoleName::Member->value;
    }

    private function definitionsForRole(string $role): array
    {
        return match ($role) {
            RoleName::Trainer->value => [
                $this->item(NotificationType::NewMemberAssigned->value, 'Assignments', 'New member assigned', 'Get alerted when a client is assigned to you.'),
                $this->item(NotificationType::MissedWorkoutAlert->value, 'Client activity', 'Client missed workout', 'Stay on top of clients who fall behind their sessions.'),
                $this->item(NotificationType::WorkoutCompleted->value, 'Client activity', 'Client completed workout', 'See when assigned clients finish planned workouts.'),
                $this->item(NotificationType::ProgressPhotoUploaded->value, 'Progress', 'Client progress upload', 'Get notified when clients upload fresh progress updates.'),
                $this->item(NotificationType::TrainerMessage->value, 'Messages', 'Messages', 'Receive chat and direct trainer-member communication alerts.'),
                $this->item(NotificationType::FollowUpReminder->value, 'Tasks', 'Follow-up reminders', 'Stay on top of note follow-ups and scheduled callbacks.'),
            ],
            RoleName::PlatformAdmin->value => [
                $this->item(NotificationType::GymApprovalAlert->value, 'Platform', 'Gym approval alerts', 'Know when new gyms need approval attention.'),
                $this->item(NotificationType::ReportedContentAlert->value, 'Safety', 'Reported content alerts', 'Placeholder for moderation or reported content workflows.', false, true),
                $this->item(NotificationType::SupportAlert->value, 'Support', 'Support alerts', 'Placeholder for support desk and escalation alerts.', false, true),
            ],
            RoleName::GymOwner->value,
            RoleName::BranchManager->value,
            RoleName::GymStaff->value => [
                $this->item(NotificationType::MembershipExpiry->value, 'Billing', 'Renewal reminders', 'Flag memberships that are about to expire.', true),
                $this->item(NotificationType::PaymentDue->value, 'Billing', 'Payment due reminders', 'Receive collection alerts for unpaid and partial memberships.', true),
                $this->item(NotificationType::TrialBooking->value, 'Leads', 'Trial lead alerts', 'Stay updated on new and changed trial requests.'),
                $this->item(NotificationType::AttendanceInactivity->value, 'Retention', 'Inactive member alerts', 'Highlight members who have not checked in recently.'),
                $this->item(NotificationType::DailyAttendanceSummary->value, 'Operations', 'Daily attendance summary', 'Placeholder for a daily attendance digest.', false, true),
                $this->item(NotificationType::PaymentCollectionSummary->value, 'Billing', 'Payment collection summary', 'Placeholder for end-of-day payment collection summaries.', false, true),
            ],
            default => [
                $this->item(NotificationType::WorkoutReminder->value, 'Workouts', 'Workout reminders', 'Get reminded to start and complete planned workouts.'),
                $this->item(NotificationType::PaymentDue->value, 'Billing', 'Payment reminders', 'Receive payment due alerts for your membership.', true),
                $this->item(NotificationType::TrainerMessage->value, 'Messages', 'Trainer messages', 'Stay updated when your assigned trainer sends a message.'),
                $this->item(NotificationType::GymAnnouncement->value, 'Gym', 'Gym announcements', 'Receive announcements from your current gym or branch.'),
                $this->item(NotificationType::ChallengeUpdate->value, 'Community', 'Challenge updates', 'Placeholder for challenge, leaderboard, and event updates.', false, true),
                $this->item(NotificationType::ProgressPhotoReminder->value, 'Progress', 'Progress reminders', 'Get nudges to upload fresh progress and stay consistent.', false),
                $this->item(NotificationType::AttendanceInactivity->value, 'Attendance', 'Attendance reminders', 'Receive reminders if your gym visits slow down.'),
                $this->item(NotificationType::TrialBooking->value, 'Trials', 'Trial reminders', 'Stay updated on trial requests and follow-ups.', false),
            ],
        };
    }

    private function item(
        string $type,
        string $category,
        string $label,
        string $description,
        bool $isCritical = false,
        bool $isPlaceholder = false,
    ): array {
        return [
            'notification_type' => $type,
            'category' => $category,
            'label' => $label,
            'description' => $description,
            'is_critical' => $isCritical,
            'is_placeholder' => $isPlaceholder,
        ];
    }
}
