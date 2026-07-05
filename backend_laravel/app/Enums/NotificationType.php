<?php

namespace App\Enums;

enum NotificationType: string
{
    case MembershipExpiry = 'membership_expiry';
    case PaymentDue = 'payment_due';
    case CustomDue = 'custom_due';
    case GymAnnouncement = 'gym_announcement';
    case TrainerAssignment = 'trainer_assignment';
    case NewMemberAssigned = 'new_member_assigned';
    case AttendanceInactivity = 'attendance_inactivity';
    case WorkoutReminder = 'workout_reminder';
    case MissedWorkoutAlert = 'missed_workout_alert';
    case WorkoutCompleted = 'workout_completed';
    case TrainerMessage = 'trainer_message';
    case TrialBooking = 'trial_booking';
    case ChallengeUpdate = 'challenge_update';
    case ProgressPhotoReminder = 'progress_photo_reminder';
    case ProgressPhotoUploaded = 'progress_photo_uploaded';
    case ClientProgressUpdate = 'client_progress_update';
    case FollowUpReminder = 'follow_up_reminder';
    case PrAchievement = 'pr_achievement';
    case DailyAttendanceSummary = 'daily_attendance_summary';
    case PaymentCollectionSummary = 'payment_collection_summary';
    case GymApprovalAlert = 'gym_approval_alert';
    case ReportedContentAlert = 'reported_content_alert';
    case SupportAlert = 'support_alert';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
