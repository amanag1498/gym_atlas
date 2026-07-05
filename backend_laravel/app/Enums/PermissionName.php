<?php

namespace App\Enums;

enum PermissionName: string
{
    case PlatformAccess = 'platform.access';
    case PlatformDashboardView = 'platform.dashboard.view';
    case PlatformGymsView = 'platform.gyms.view';
    case PlatformGymsManage = 'platform.gyms.manage';
    case PlatformUsersView = 'platform.users.view';
    case PlatformLocationsManage = 'platform.locations.manage';
    case PlatformFacilitiesManage = 'platform.facilities.manage';
    case PlatformFitnessGoalsManage = 'platform.fitness_goals.manage';
    case PlatformBannersManage = 'platform.banners.manage';
    case PlatformPublicListingsManage = 'platform.public_listings.manage';
    case PlatformAdminsView = 'platform_admins.view';
    case PlatformAdminsManage = 'platform_admins.manage';
    case GymDashboardView = 'gym.dashboard.view';
    case GymsView = 'gym.view';
    case GymsManage = 'gym.manage';
    case GymProfileManage = 'gym.profile.manage';
    case BranchesView = 'branch.view';
    case BranchesManage = 'branch.manage';
    case StaffManage = 'staff.manage';
    case TrainersView = 'trainer.view';
    case TrainersManage = 'trainer.manage';
    case TrainerSelfManage = 'trainer.self.manage';
    case MembersView = 'member.view';
    case MembersManage = 'member.manage';
    case MembershipPlansView = 'membership_plan.view';
    case MembershipPlansManage = 'membership_plan.manage';
    case MembershipsView = 'membership.view';
    case MembershipsManage = 'membership.manage';
    case PaymentsView = 'payment.view';
    case PaymentsManage = 'payment.manage';
    case EditCustomFee = 'edit_custom_fee';
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case TrialRequestsView = 'trial_request.view';
    case TrialRequestsManage = 'trial_request.manage';
    case ExercisesView = 'exercise.view';
    case ExercisesManage = 'exercise.manage';
    case WorkoutTemplatesView = 'workout_template.view';
    case WorkoutTemplatesManage = 'workout_template.manage';
    case WorkoutPlansView = 'workout_plan.view';
    case WorkoutPlansManage = 'workout_plan.manage';
    case WorkoutSessionsView = 'workout_session.view';
    case WorkoutSessionsManage = 'workout_session.manage';
    case ProgressView = 'progress.view';
    case ProgressManage = 'progress.manage';
    case AnnouncementsView = 'announcement.view';
    case AnnouncementsManage = 'announcement.manage';
    case NotificationsManage = 'notification.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
