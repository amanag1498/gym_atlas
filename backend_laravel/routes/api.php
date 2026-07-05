<?php

use App\Http\Controllers\Api\Gym\Admin\AttendanceController;
use App\Http\Controllers\Api\Chat\TrainerMemberChatController;
use App\Http\Controllers\Api\Gym\Admin\AuditLogController as GymAuditLogController;
use App\Http\Controllers\Api\Gym\Admin\BranchController as GymBranchController;
use App\Http\Controllers\Api\Gym\Admin\DashboardController as GymDashboardController;
use App\Http\Controllers\Api\Gym\Admin\GymProfileController;
use App\Http\Controllers\Api\Gym\Admin\MemberController as GymMemberController;
use App\Http\Controllers\Api\Gym\Admin\SettingController as GymSettingController;
use App\Http\Controllers\Api\Gym\Admin\ReportController as GymReportController;
use App\Http\Controllers\Api\Gym\Admin\StaffController as GymStaffController;
use App\Http\Controllers\Api\Gym\Admin\TrainerController as GymTrainerController;
use App\Http\Controllers\Api\Gym\Admin\TrialRequestController as GymTrialRequestController;
use App\Http\Controllers\Api\Gym\Billing\CustomFeeAuditLogController;
use App\Http\Controllers\Api\Gym\Billing\MemberMembershipController;
use App\Http\Controllers\Api\Gym\Billing\MembershipPlanController;
use App\Http\Controllers\Api\Gym\Billing\PaymentController;
use App\Http\Controllers\Api\Gym\Billing\PaymentReceiptController;
use App\Http\Controllers\Api\Gym\Communication\AnnouncementController as GymAnnouncementController;
use App\Http\Controllers\Api\Gym\Communication\ReminderController as GymReminderController;
use App\Http\Controllers\Api\Gym\GymContextController;
use App\Http\Controllers\Api\Member\AttendanceController as MemberAttendanceController;
use App\Http\Controllers\Api\Member\FavoriteGymController;
use App\Http\Controllers\Api\Member\MemberContextController;
use App\Http\Controllers\Api\Member\MemberGymInvitationController;
use App\Http\Controllers\Api\Member\MemberMembershipController as MemberAppMembershipController;
use App\Http\Controllers\Api\Member\MemberProfileController;
use App\Http\Controllers\Api\Member\MemberStepController;
use App\Http\Controllers\Api\Member\MemberTrainerController;
use App\Http\Controllers\Api\Member\ProgressController as MemberProgressController;
use App\Http\Controllers\Api\Member\WorkoutController as MemberWorkoutController;
use App\Http\Controllers\Api\PlatformAdmin\ExerciseController as PlatformExerciseController;
use App\Http\Controllers\Api\PlatformAdmin\AnnouncementController as PlatformAnnouncementController;
use App\Http\Controllers\Api\PlatformAdmin\AuditLogController as PlatformAuditLogController;
use App\Http\Controllers\Api\PlatformAdmin\CatalogController;
use App\Http\Controllers\Api\PlatformAdmin\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Api\PlatformAdmin\GymController as PlatformGymController;
use App\Http\Controllers\Api\PlatformAdmin\GymOwnerController as PlatformGymOwnerController;
use App\Http\Controllers\Api\PlatformAdmin\ListingController as PlatformListingController;
use App\Http\Controllers\Api\PlatformAdmin\PlatformAdminContextController;
use App\Http\Controllers\Api\PlatformAdmin\ReportController as PlatformReportController;
use App\Http\Controllers\Api\PlatformAdmin\SettingController as PlatformSettingController;
use App\Http\Controllers\Api\PlatformAdmin\UserController as PlatformUserController;
use App\Http\Controllers\Api\PlatformAdmin\WorkoutBookController as PlatformWorkoutBookController;
use App\Http\Controllers\Api\Public\AuthController;
use App\Http\Controllers\Api\Public\DiscoveryController;
use App\Http\Controllers\Api\Public\FcmTokenController;
use App\Http\Controllers\Api\Public\NotificationController as PublicNotificationController;
use App\Http\Controllers\Api\Public\PublicContextController;
use App\Http\Controllers\Api\Public\TrialRequestController as PublicTrialRequestController;
use App\Http\Controllers\Api\Trainer\AssignedMemberController as TrainerAssignedMemberController;
use App\Http\Controllers\Api\Trainer\AnnouncementController as TrainerAnnouncementController;
use App\Http\Controllers\Api\Trainer\ExerciseController as TrainerExerciseController;
use App\Http\Controllers\Api\Trainer\TaskController as TrainerTaskController;
use App\Http\Controllers\Api\Trainer\TrainerMemberNoteController;
use App\Http\Controllers\Api\Trainer\TrainerContextController;
use App\Http\Controllers\Api\Trainer\TrainerNotificationController;
use App\Http\Controllers\Api\Trainer\TrainerProfileController;
use App\Http\Controllers\Api\Trainer\TrialRequestController as TrainerTrialRequestController;
use App\Http\Controllers\Api\Trainer\WorkoutPlanController as TrainerWorkoutPlanController;
use App\Http\Controllers\Api\Trainer\WorkoutTemplateController as TrainerWorkoutTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('health', [PublicContextController::class, 'health']);
    Route::post('auth/google/login', [AuthController::class, 'googleLogin']);
    Route::post('auth/firebase/login', [AuthController::class, 'firebaseLogin']);
    Route::get('discovery/gyms', [DiscoveryController::class, 'index']);
    Route::get('discovery/gyms/nearby', [DiscoveryController::class, 'nearby'])->name('public.discovery.nearby');
    Route::get('discovery/cities/{city}/gyms', [DiscoveryController::class, 'cityGyms']);
    Route::get('discovery/gyms/{slug}', [DiscoveryController::class, 'show']);
    Route::post('trial-requests', [PublicTrialRequestController::class, 'store']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/active-role', [AuthController::class, 'switchActiveRole']);
        Route::get('notifications', [PublicNotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [PublicNotificationController::class, 'markRead']);
        Route::post('notifications/{notification}/unread', [PublicNotificationController::class, 'markUnread']);
        Route::post('notifications/read-all', [PublicNotificationController::class, 'markAllRead']);
        Route::get('notification-preferences', [PublicNotificationController::class, 'preferences']);
        Route::put('notification-preferences', [PublicNotificationController::class, 'updatePreferences']);
        Route::post('fcm-tokens', [FcmTokenController::class, 'store']);
        Route::delete('fcm-tokens', [FcmTokenController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('notifications', [PublicNotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [PublicNotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [PublicNotificationController::class, 'markAllRead']);
    Route::get('notification-preferences', [PublicNotificationController::class, 'preferences']);
    Route::put('notification-preferences', [PublicNotificationController::class, 'updatePreferences']);
    Route::post('fcm-tokens', [FcmTokenController::class, 'store']);
    Route::delete('fcm-tokens', [FcmTokenController::class, 'destroy']);
    Route::get('chat/conversations', [TrainerMemberChatController::class, 'conversations']);
    Route::get('chat/messages', [TrainerMemberChatController::class, 'index']);
    Route::post('chat/messages', [TrainerMemberChatController::class, 'store']);
    Route::post('chat/read', [TrainerMemberChatController::class, 'markRead']);
});

Route::prefix('internal')
    ->group(function (): void {
        Route::post('chat/messages', [TrainerMemberChatController::class, 'internalStore']);
        Route::post('chat/read', [TrainerMemberChatController::class, 'internalRead']);
    });

Route::prefix('platform-admin')
    ->middleware([
        'auth:sanctum',
        'role:platform_admin',
        'active_role:platform_admin',
        'permission:platform.access|platform_admins.view',
    ])
    ->group(function (): void {
        Route::get('dashboard', PlatformDashboardController::class)
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports', [PlatformReportController::class, 'index'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports/gyms', [PlatformReportController::class, 'gyms'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports/users', [PlatformReportController::class, 'users'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports/payments', [PlatformReportController::class, 'payments'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports/attendance', [PlatformReportController::class, 'attendance'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('reports/custom-fees', [PlatformReportController::class, 'customFees'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('settings', [PlatformSettingController::class, 'index'])
            ->middleware('permission:platform.dashboard.view');
        Route::put('settings', [PlatformSettingController::class, 'update'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('audit-logs', [PlatformAuditLogController::class, 'index'])
            ->middleware('permission:platform.dashboard.view');
        Route::get('context', PlatformAdminContextController::class);
        Route::get('gyms', [PlatformGymController::class, 'index'])
            ->middleware('permission:platform.gyms.view');
        Route::post('gyms', [PlatformGymController::class, 'store'])
            ->middleware('permission:platform.gyms.manage');
        Route::get('gyms/{gym}', [PlatformGymController::class, 'show'])
            ->middleware('permission:platform.gyms.view');
        Route::put('gyms/{gym}', [PlatformGymController::class, 'update'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/approve', [PlatformGymController::class, 'approve'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/reject', [PlatformGymController::class, 'reject'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/activate', [PlatformGymController::class, 'activate'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/deactivate', [PlatformGymController::class, 'deactivate'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/verify', [PlatformGymController::class, 'verify'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/feature', [PlatformGymController::class, 'feature'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/promote', [PlatformGymController::class, 'promote'])
            ->middleware('permission:platform.gyms.manage');
        Route::post('gyms/{gym}/hide-listing', [PlatformGymController::class, 'hideListing'])
            ->middleware('permission:platform.public_listings.manage');
        Route::post('gyms/{gym}/show-listing', [PlatformGymController::class, 'showListing'])
            ->middleware('permission:platform.public_listings.manage');
        Route::patch('gyms/{gym}/approval', [PlatformGymController::class, 'updateApproval'])
            ->middleware('permission:platform.gyms.manage');
        Route::patch('gyms/{gym}/status', [PlatformGymController::class, 'updateStatus'])
            ->middleware('permission:platform.gyms.manage');
        Route::patch('gyms/{gym}/verification', [PlatformGymController::class, 'updateVerification'])
            ->middleware('permission:platform.gyms.manage');
        Route::patch('gyms/{gym}/listing-flags', [PlatformGymController::class, 'updateListingFlags'])
            ->middleware('permission:platform.gyms.manage');
        Route::patch('gyms/{gym}/public-listing', [PlatformGymController::class, 'updatePublicListing'])
            ->middleware('permission:platform.public_listings.manage');
        Route::get('listings', [PlatformListingController::class, 'index'])
            ->middleware('permission:platform.public_listings.manage');
        Route::get('featured-gyms', [PlatformListingController::class, 'featured'])
            ->middleware('permission:platform.public_listings.manage');
        Route::get('promoted-gyms', [PlatformListingController::class, 'promoted'])
            ->middleware('permission:platform.public_listings.manage');
        Route::get('gym-owners', [PlatformGymOwnerController::class, 'index'])
            ->middleware('permission:platform.users.view');
        Route::post('gym-owners', [PlatformGymOwnerController::class, 'store'])
            ->middleware('permission:platform.users.view');
        Route::get('gym-owners/{user}', [PlatformGymOwnerController::class, 'show'])
            ->middleware('permission:platform.users.view');
        Route::put('gym-owners/{user}', [PlatformGymOwnerController::class, 'update'])
            ->middleware('permission:platform.users.view');
        Route::post('gym-owners/{user}/activate', [PlatformGymOwnerController::class, 'activate'])
            ->middleware('permission:platform.users.view');
        Route::post('gym-owners/{user}/deactivate', [PlatformGymOwnerController::class, 'deactivate'])
            ->middleware('permission:platform.users.view');
        Route::get('users', [PlatformUserController::class, 'index'])
            ->middleware('permission:platform.users.view');
        Route::get('trainers', [PlatformUserController::class, 'trainers'])
            ->middleware('permission:platform.users.view');
        Route::get('members', [PlatformUserController::class, 'members'])
            ->middleware('permission:platform.users.view');
        Route::get('users/{user}', [PlatformUserController::class, 'show'])
            ->middleware('permission:platform.users.view');
        Route::get('workout-books', [PlatformWorkoutBookController::class, 'index'])
            ->middleware('permission:workout_template.view');
        Route::post('workout-books', [PlatformWorkoutBookController::class, 'store'])
            ->middleware('permission:workout_template.manage');
        Route::get('workout-books/{workoutBook}', [PlatformWorkoutBookController::class, 'show'])
            ->middleware('permission:workout_template.view');
        Route::put('workout-books/{workoutBook}', [PlatformWorkoutBookController::class, 'update'])
            ->middleware('permission:workout_template.manage');
        Route::delete('workout-books/{workoutBook}', [PlatformWorkoutBookController::class, 'destroy'])
            ->middleware('permission:workout_template.manage');
        Route::post('users/{user}/activate', [PlatformUserController::class, 'activate'])
            ->middleware('permission:platform.users.view');
        Route::post('users/{user}/deactivate', [PlatformUserController::class, 'deactivate'])
            ->middleware('permission:platform.users.view');
        Route::get('cities', [CatalogController::class, 'cities'])
            ->middleware('permission:platform.locations.manage');
        Route::post('cities', [CatalogController::class, 'storeCity'])
            ->middleware('permission:platform.locations.manage');
        Route::put('cities/{city}', [CatalogController::class, 'updateCity'])
            ->middleware('permission:platform.locations.manage');
        Route::delete('cities/{city}', [CatalogController::class, 'deleteCity'])
            ->middleware('permission:platform.locations.manage');
        Route::get('facilities', [CatalogController::class, 'facilities'])
            ->middleware('permission:platform.facilities.manage');
        Route::post('facilities', [CatalogController::class, 'storeFacility'])
            ->middleware('permission:platform.facilities.manage');
        Route::get('facilities/{facility}', [CatalogController::class, 'showFacility'])
            ->middleware('permission:platform.facilities.manage');
        Route::put('facilities/{facility}', [CatalogController::class, 'updateFacility'])
            ->middleware('permission:platform.facilities.manage');
        Route::post('facilities/{facility}/toggle-status', [CatalogController::class, 'toggleFacilityStatus'])
            ->middleware('permission:platform.facilities.manage');
        Route::delete('facilities/{facility}', [CatalogController::class, 'deleteFacility'])
            ->middleware('permission:platform.facilities.manage');
        Route::get('fitness-goals', [CatalogController::class, 'fitnessGoals'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::post('fitness-goals', [CatalogController::class, 'storeFitnessGoal'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::get('fitness-goals/{fitnessGoal}', [CatalogController::class, 'showFitnessGoal'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::put('fitness-goals/{fitnessGoal}', [CatalogController::class, 'updateFitnessGoal'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::post('fitness-goals/{fitnessGoal}/toggle-status', [CatalogController::class, 'toggleFitnessGoalStatus'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::delete('fitness-goals/{fitnessGoal}', [CatalogController::class, 'deleteFitnessGoal'])
            ->middleware('permission:platform.fitness_goals.manage');
        Route::get('trainer-specializations', [CatalogController::class, 'trainerSpecializations'])
            ->middleware('permission:platform.users.view');
        Route::post('trainer-specializations', [CatalogController::class, 'storeTrainerSpecialization'])
            ->middleware('permission:platform.users.view');
        Route::get('trainer-specializations/{trainerSpecialization}', [CatalogController::class, 'showTrainerSpecialization'])
            ->middleware('permission:platform.users.view');
        Route::put('trainer-specializations/{trainerSpecialization}', [CatalogController::class, 'updateTrainerSpecialization'])
            ->middleware('permission:platform.users.view');
        Route::post('trainer-specializations/{trainerSpecialization}/toggle-status', [CatalogController::class, 'toggleTrainerSpecializationStatus'])
            ->middleware('permission:platform.users.view');
        Route::delete('trainer-specializations/{trainerSpecialization}', [CatalogController::class, 'deleteTrainerSpecialization'])
            ->middleware('permission:platform.users.view');
        Route::get('banners', [CatalogController::class, 'banners'])
            ->middleware('permission:platform.banners.manage');
        Route::post('banners', [CatalogController::class, 'storeBanner'])
            ->middleware('permission:platform.banners.manage');
        Route::put('banners/{banner}', [CatalogController::class, 'updateBanner'])
            ->middleware('permission:platform.banners.manage');
        Route::delete('banners/{banner}', [CatalogController::class, 'deleteBanner'])
            ->middleware('permission:platform.banners.manage');
        Route::get('exercises', [PlatformExerciseController::class, 'index'])
            ->middleware('permission:exercise.view|exercise.manage');
        Route::post('exercises', [PlatformExerciseController::class, 'store'])
            ->middleware('permission:exercise.manage');
        Route::put('exercises/{exercise}', [PlatformExerciseController::class, 'update'])
            ->middleware('permission:exercise.manage');
        Route::get('announcements', [PlatformAnnouncementController::class, 'index'])
            ->middleware('permission:announcement.view|announcement.manage');
        Route::post('announcements', [PlatformAnnouncementController::class, 'store'])
            ->middleware('permission:announcement.manage');
    });

Route::prefix('gym')
    ->middleware([
        'auth:sanctum',
        'role:platform_admin|gym_owner|branch_manager|gym_staff',
        'active_role:platform_admin,gym_owner,branch_manager,gym_staff',
        'permission:gym.view',
        'gym_scope',
        'branch_scope',
    ])
    ->group(function (): void {
        Route::get('dashboard', GymDashboardController::class)
            ->middleware('permission:gym.dashboard.view');
        Route::get('context', GymContextController::class);
        Route::get('profile', [GymProfileController::class, 'show'])
            ->middleware('permission:gym.view');
        Route::put('profile', [GymProfileController::class, 'update'])
            ->middleware('permission:gym.profile.manage');
        Route::get('public-listing-settings', [GymProfileController::class, 'publicListingSettings'])
            ->middleware('permission:gym.view');
        Route::put('public-listing-settings', [GymProfileController::class, 'updatePublicListingSettings'])
            ->middleware('permission:gym.profile.manage');
        Route::get('settings', [GymSettingController::class, 'index']);
        Route::put('settings', [GymSettingController::class, 'update']);
        Route::get('audit-logs', [GymAuditLogController::class, 'index']);
        Route::get('branches', [GymBranchController::class, 'index'])
            ->middleware('permission:branch.view');
        Route::post('branches', [GymBranchController::class, 'store'])
            ->middleware('permission:branch.manage');
        Route::get('branches/{branch}', [GymBranchController::class, 'show'])
            ->middleware('permission:branch.view');
        Route::put('branches/{branch}', [GymBranchController::class, 'update'])
            ->middleware('permission:branch.manage');
        Route::delete('branches/{branch}', [GymBranchController::class, 'destroy'])
            ->middleware('permission:branch.manage');
        Route::post('branches/{branch}/toggle-status', [GymBranchController::class, 'toggleStatus'])
            ->middleware('permission:branch.manage');
        Route::get('staff', [GymStaffController::class, 'index'])
            ->middleware('permission:staff.manage');
        Route::post('staff', [GymStaffController::class, 'store'])
            ->middleware('permission:staff.manage');
        Route::get('staff/{staff}', [GymStaffController::class, 'show'])
            ->middleware('permission:staff.manage');
        Route::put('staff/{staff}', [GymStaffController::class, 'update'])
            ->middleware('permission:staff.manage');
        Route::post('staff/{staff}/activate', [GymStaffController::class, 'activate'])
            ->middleware('permission:staff.manage');
        Route::post('staff/{staff}/deactivate', [GymStaffController::class, 'deactivate'])
            ->middleware('permission:staff.manage');
        Route::delete('staff/{staff}', [GymStaffController::class, 'destroy'])
            ->middleware('permission:staff.manage');
        Route::get('trainers', [GymTrainerController::class, 'index'])
            ->middleware('permission:trainer.view');
        Route::post('trainers', [GymTrainerController::class, 'store'])
            ->middleware('permission:trainer.manage');
        Route::get('trainers/{trainer}', [GymTrainerController::class, 'show'])
            ->middleware('permission:trainer.view');
        Route::put('trainers/{trainer}', [GymTrainerController::class, 'update'])
            ->middleware('permission:trainer.manage');
        Route::post('trainers/{trainer}/activate', [GymTrainerController::class, 'activate'])
            ->middleware('permission:trainer.manage');
        Route::post('trainers/{trainer}/deactivate', [GymTrainerController::class, 'deactivate'])
            ->middleware('permission:trainer.manage');
        Route::post('trainers/{trainer}/assign-members', [GymTrainerController::class, 'assignMembers'])
            ->middleware('permission:trainer.manage');
        Route::delete('trainers/{trainer}', [GymTrainerController::class, 'destroy'])
            ->middleware('permission:trainer.manage');
        Route::get('members', [GymMemberController::class, 'index'])
            ->middleware('permission:member.view');
        Route::post('members', [GymMemberController::class, 'store'])
            ->middleware('permission:member.manage');
        Route::get('members/{member}', [GymMemberController::class, 'show'])
            ->middleware('permission:member.view');
        Route::put('members/{member}', [GymMemberController::class, 'update'])
            ->middleware('permission:member.manage');
        Route::post('members/{member}/activate', [GymMemberController::class, 'activate'])
            ->middleware('permission:member.manage');
        Route::post('members/{member}/deactivate', [GymMemberController::class, 'deactivate'])
            ->middleware('permission:member.manage');
        Route::post('members/{member}/assign-trainer', [GymMemberController::class, 'assignTrainer'])
            ->middleware('permission:member.manage');
        Route::delete('members/{member}', [GymMemberController::class, 'destroy'])
            ->middleware('permission:member.manage');
        Route::get('attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:attendance.view|attendance.manage');
        Route::get('attendance/today', [AttendanceController::class, 'today'])
            ->middleware('permission:attendance.view|attendance.manage');
        Route::get('attendance/branch-summary', [AttendanceController::class, 'branchWise'])
            ->middleware('permission:attendance.view|attendance.manage');
        Route::get('attendance/members/{member}', [AttendanceController::class, 'memberHistory'])
            ->middleware('permission:attendance.view|attendance.manage');
        Route::get('members/{member}/attendance', [AttendanceController::class, 'memberHistory'])
            ->middleware('permission:attendance.view|attendance.manage');
        Route::post('attendance/manual', [AttendanceController::class, 'manual'])
            ->middleware('permission:attendance.manage');
        Route::post('attendance/scan', [AttendanceController::class, 'biometricScan'])
            ->middleware('permission:attendance.manage');
        Route::post('attendance/biometric-scan', [AttendanceController::class, 'biometricScan'])
            ->middleware('permission:attendance.manage');
        Route::get('membership-plans', [MembershipPlanController::class, 'index'])
            ->middleware('permission:membership_plan.view|membership_plan.manage');
        Route::post('membership-plans', [MembershipPlanController::class, 'store'])
            ->middleware('permission:membership_plan.manage');
        Route::get('membership-plans/{membershipPlan}', [MembershipPlanController::class, 'show'])
            ->middleware('permission:membership_plan.view|membership_plan.manage');
        Route::put('membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update'])
            ->middleware('permission:membership_plan.manage');
        Route::post('membership-plans/{membershipPlan}/activate', [MembershipPlanController::class, 'activate'])
            ->middleware('permission:membership_plan.manage');
        Route::post('membership-plans/{membershipPlan}/deactivate', [MembershipPlanController::class, 'deactivate'])
            ->middleware('permission:membership_plan.manage');
        Route::get('member-memberships', [MemberMembershipController::class, 'index'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::get('custom-fees', [MemberMembershipController::class, 'customFeesIndex'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::get('custom-fees/audit-logs', [MemberMembershipController::class, 'customFeeAuditLogs'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::post('member-memberships', [MemberMembershipController::class, 'store'])
            ->middleware('permission:membership.manage');
        Route::get('member-memberships/{memberMembership}', [MemberMembershipController::class, 'show'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::post('member-memberships/{memberMembership}/custom-fee', [MemberMembershipController::class, 'updateCustomFee']);
        Route::post('member-memberships/{memberMembership}/renew', [MemberMembershipController::class, 'renew'])
            ->middleware('permission:membership.manage');
        Route::post('member-memberships/{memberMembership}/freeze', [MemberMembershipController::class, 'freeze'])
            ->middleware('permission:membership.manage');
        Route::post('member-memberships/{memberMembership}/extend', [MemberMembershipController::class, 'extend'])
            ->middleware('permission:membership.manage');
        Route::post('member-memberships/{memberMembership}/cancel', [MemberMembershipController::class, 'cancel'])
            ->middleware('permission:membership.manage');
        Route::get('memberships', [MemberMembershipController::class, 'index'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::get('members/{member}/custom-fee', [MemberMembershipController::class, 'customFeeForMember'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::post('members/{member}/custom-fee', [MemberMembershipController::class, 'updateCustomFeeForMember']);
        Route::post('members/{member}/assign-membership', [MemberMembershipController::class, 'assignForMember'])
            ->middleware('permission:membership.manage');
        Route::post('memberships/{memberMembership}/renew', [MemberMembershipController::class, 'renew'])
            ->middleware('permission:membership.manage');
        Route::post('memberships/{memberMembership}/freeze', [MemberMembershipController::class, 'freeze'])
            ->middleware('permission:membership.manage');
        Route::post('memberships/{memberMembership}/extend', [MemberMembershipController::class, 'extend'])
            ->middleware('permission:membership.manage');
        Route::post('memberships/{memberMembership}/cancel', [MemberMembershipController::class, 'cancel'])
            ->middleware('permission:membership.manage');
        Route::get('member-memberships/{memberMembership}/custom-fee-audits', [CustomFeeAuditLogController::class, 'index'])
            ->middleware('permission:membership.view|membership.manage|payment.view|payment.manage');
        Route::get('payments', [PaymentController::class, 'index'])
            ->middleware('permission:payment.view|payment.manage');
        Route::post('payments', [PaymentController::class, 'store'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('payments/reports', [PaymentController::class, 'reports'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('reports', [GymReportController::class, 'index']);
        Route::get('reports/revenue', [GymReportController::class, 'revenue']);
        Route::get('reports/dues', [GymReportController::class, 'dues']);
        Route::get('reports/memberships', [GymReportController::class, 'memberships']);
        Route::get('reports/attendance', [GymReportController::class, 'attendance']);
        Route::get('reports/trainers', [GymReportController::class, 'trainers']);
        Route::get('reports/custom-fees', [GymReportController::class, 'customFees']);
        Route::get('reports/leads', [GymReportController::class, 'leads']);
        Route::get('payments/{payment}', [PaymentController::class, 'show'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('dues', [PaymentController::class, 'dues'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('members/{member}/payments', [PaymentController::class, 'memberPayments'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('member-memberships/{memberMembership}/payments', [PaymentController::class, 'history'])
            ->middleware('permission:payment.view|payment.manage');
        Route::post('member-memberships/{memberMembership}/payments', [PaymentController::class, 'storeForMembership'])
            ->middleware('permission:payment.view|payment.manage');
        Route::post('member-memberships/{memberMembership}/mark-paid', [PaymentController::class, 'markPaid'])
            ->middleware('permission:payment.view|payment.manage');
        Route::post('member-memberships/{memberMembership}/mark-unpaid', [PaymentController::class, 'markUnpaid'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('payments/{payment}/receipt', [PaymentReceiptController::class, 'show'])
            ->middleware('permission:payment.view|payment.manage');
        Route::get('announcements', [GymAnnouncementController::class, 'index'])
            ->middleware('permission:announcement.view|announcement.manage');
        Route::post('announcements', [GymAnnouncementController::class, 'store'])
            ->middleware('permission:announcement.view|announcement.manage|notification.manage');
        Route::get('announcements/{announcement}', [GymAnnouncementController::class, 'show'])
            ->middleware('permission:announcement.view|announcement.manage');
        Route::delete('announcements/{announcement}', [GymAnnouncementController::class, 'destroy'])
            ->middleware('permission:announcement.view|announcement.manage|notification.manage');
        Route::get('scheduled-reminders', [GymReminderController::class, 'index'])
            ->middleware('permission:notification.manage');
        Route::post('scheduled-reminders/run-due', [GymReminderController::class, 'runDue'])
            ->middleware('permission:notification.manage');
        Route::get('trial-requests', [GymTrialRequestController::class, 'index'])
            ->middleware('permission:trial_request.view|trial_request.manage');
        Route::get('trial-requests/{trialRequest}', [GymTrialRequestController::class, 'show'])
            ->middleware('permission:trial_request.view|trial_request.manage');
        Route::put('trial-requests/{trialRequest}', [GymTrialRequestController::class, 'update'])
            ->middleware('permission:trial_request.manage');
        Route::post('trial-requests/{trialRequest}/accept', [GymTrialRequestController::class, 'accept'])
            ->middleware('permission:trial_request.manage');
        Route::post('trial-requests/{trialRequest}/reject', [GymTrialRequestController::class, 'reject'])
            ->middleware('permission:trial_request.manage');
        Route::post('trial-requests/{trialRequest}/complete', [GymTrialRequestController::class, 'complete'])
            ->middleware('permission:trial_request.manage');
        Route::post('trial-requests/{trialRequest}/convert', [GymTrialRequestController::class, 'convert'])
            ->middleware('permission:trial_request.manage');
        Route::post('trial-requests/{trialRequest}/assign-trainer', [GymTrialRequestController::class, 'assignTrainer'])
            ->middleware('permission:trial_request.manage');
    });

Route::prefix('trainer')
    ->middleware([
        'auth:sanctum',
        'role:platform_admin|gym_owner|branch_manager|trainer',
        'active_role:platform_admin,gym_owner,branch_manager,trainer',
        'permission:trainer.view',
        'gym_scope',
        'branch_scope',
    ])
    ->group(function (): void {
        Route::get('context', TrainerContextController::class);
        Route::get('profile', [TrainerProfileController::class, 'show'])
            ->middleware('permission:trainer.view');
        Route::put('profile', [TrainerProfileController::class, 'update'])
            ->middleware('permission:trainer.self.manage|trainer.manage');
        Route::post('profile/photo', [TrainerProfileController::class, 'uploadPhoto'])
            ->middleware('permission:trainer.self.manage|trainer.manage');
        Route::post('profile/certifications/upload', [TrainerProfileController::class, 'uploadCertificationFile'])
            ->middleware('permission:trainer.self.manage|trainer.manage');
        Route::get('assigned-members', [TrainerAssignedMemberController::class, 'index'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('assigned-members/{member}', [TrainerAssignedMemberController::class, 'show'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('assigned-members/{member}/attendance', [TrainerAssignedMemberController::class, 'attendance'])
            ->middleware('permission:trainer.view|attendance.view');
        Route::get('assigned-members/{member}/progress', [TrainerAssignedMemberController::class, 'progress'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('assigned-members/{member}/notes', [TrainerMemberNoteController::class, 'index'])
            ->middleware('permission:trainer.view|member.view');
        Route::post('assigned-members/{member}/notes', [TrainerMemberNoteController::class, 'store'])
            ->middleware('permission:trainer.self.manage');
        Route::put('notes/{trainerMemberNote}', [TrainerMemberNoteController::class, 'update'])
            ->middleware('permission:trainer.self.manage');
        Route::post('notes/{trainerMemberNote}/complete', [TrainerMemberNoteController::class, 'complete'])
            ->middleware('permission:trainer.self.manage');
        Route::get('today-clients', [TrainerTaskController::class, 'todayClients'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('pending-follow-ups', [TrainerTaskController::class, 'pendingFollowUps'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('tasks', [TrainerTaskController::class, 'summary'])
            ->middleware('permission:trainer.view|member.view');
        Route::get('assigned-members/{member}/workout-plans', [TrainerAssignedMemberController::class, 'workoutPlans'])
            ->middleware('permission:workout_plan.view|workout_plan.manage');
        Route::get('assigned-members/{member}/workout-logbook', [TrainerAssignedMemberController::class, 'workoutLogbook'])
            ->middleware('permission:workout_session.view|progress.view');
        Route::get('exercises', [TrainerExerciseController::class, 'index'])
            ->middleware('permission:exercise.view|exercise.manage');
        Route::post('exercises', [TrainerExerciseController::class, 'store'])
            ->middleware('permission:exercise.manage');
        Route::get('workout-templates', [TrainerWorkoutTemplateController::class, 'index'])
            ->middleware('permission:workout_template.view|workout_template.manage');
        Route::post('workout-templates', [TrainerWorkoutTemplateController::class, 'store'])
            ->middleware('permission:workout_template.manage');
        Route::get('workout-templates/{workoutTemplate}', [TrainerWorkoutTemplateController::class, 'show'])
            ->middleware('permission:workout_template.view|workout_template.manage');
        Route::put('workout-templates/{workoutTemplate}', [TrainerWorkoutTemplateController::class, 'update'])
            ->middleware('permission:workout_template.manage');
        Route::post('workout-templates/{workoutTemplate}/assign', [TrainerWorkoutTemplateController::class, 'assign'])
            ->middleware('permission:workout_plan.manage');
        Route::get('workout-plans', [TrainerWorkoutPlanController::class, 'index'])
            ->middleware('permission:workout_plan.view|workout_plan.manage');
        Route::post('workout-plans', [TrainerWorkoutPlanController::class, 'store'])
            ->middleware('permission:workout_plan.manage');
        Route::get('workout-plans/{workoutPlan}', [TrainerWorkoutPlanController::class, 'show'])
            ->middleware('permission:workout_plan.view|workout_plan.manage');
        Route::put('workout-plans/{workoutPlan}', [TrainerWorkoutPlanController::class, 'update'])
            ->middleware('permission:workout_plan.manage');
        Route::delete('workout-plans/{workoutPlan}', [TrainerWorkoutPlanController::class, 'destroy'])
            ->middleware('permission:workout_plan.manage');
        Route::get('notifications', [TrainerNotificationController::class, 'index'])
            ->middleware('permission:trainer.view');
        Route::post('notifications/{notification}/read', [TrainerNotificationController::class, 'markRead'])
            ->middleware('permission:trainer.self.manage');
        Route::post('announcements', [TrainerAnnouncementController::class, 'store'])
            ->middleware('permission:notification.manage');
        Route::get('trial-requests', [TrainerTrialRequestController::class, 'index'])
            ->middleware('permission:trial_request.view|trial_request.manage');
        Route::get('trial-requests/{trialRequest}', [TrainerTrialRequestController::class, 'show'])
            ->middleware('permission:trial_request.view|trial_request.manage');
        Route::put('trial-requests/{trialRequest}', [TrainerTrialRequestController::class, 'update'])
            ->middleware('permission:trial_request.manage');
    });

Route::prefix('member')
    ->middleware([
        'auth:sanctum',
        'role:member',
        'active_role:member',
        'permission:member.view',
    ])
    ->group(function (): void {
        Route::post('trial-requests', [\App\Http\Controllers\Api\Member\TrialRequestController::class, 'store']);
        Route::get('gym-invitations', [MemberGymInvitationController::class, 'index']);
        Route::post('gym-invitations/{invitation}/accept', [MemberGymInvitationController::class, 'accept']);
        Route::post('gym-invitations/{invitation}/reject', [MemberGymInvitationController::class, 'reject']);
    });

Route::prefix('member')
    ->middleware([
        'auth:sanctum',
        'role:member',
        'active_role:member',
        'permission:member.view',
        'gym_scope',
        'branch_scope',
    ])
    ->group(function (): void {
        Route::get('context', MemberContextController::class);
        Route::get('profile', [MemberProfileController::class, 'show']);
        Route::put('profile', [MemberProfileController::class, 'update']);
        Route::get('favorite-gyms', [FavoriteGymController::class, 'index']);
        Route::post('favorite-gyms/{publicGym}', [FavoriteGymController::class, 'store']);
        Route::delete('favorite-gyms/{publicGym}', [FavoriteGymController::class, 'destroy']);
        Route::get('membership', [MemberAppMembershipController::class, 'show']);
        Route::post('membership/leave', [MemberAppMembershipController::class, 'leave']);
        Route::get('trainer', [MemberTrainerController::class, 'show']);
        Route::get('attendance/biometric-profile', [MemberAttendanceController::class, 'biometricProfile']);
        Route::get('attendance', [MemberAttendanceController::class, 'history']);
        Route::get('attendance/status', [MemberAttendanceController::class, 'status']);
        Route::get('attendance/history', [MemberAttendanceController::class, 'history']);
        Route::get('workout-plans', [MemberWorkoutController::class, 'plans'])
            ->middleware('permission:workout_plan.view');
        Route::post('workout-plans', [MemberWorkoutController::class, 'storePlan'])
            ->middleware('permission:workout_plan.manage|workout_session.manage');
        Route::get('workout-plans/{workoutPlan}', [MemberWorkoutController::class, 'showPlan'])
            ->middleware('permission:workout_plan.view');
        Route::put('workout-plans/{workoutPlan}', [MemberWorkoutController::class, 'updatePlan'])
            ->middleware('permission:workout_plan.manage|workout_session.manage');
        Route::delete('workout-plans/{workoutPlan}', [MemberWorkoutController::class, 'destroyPlan'])
            ->middleware('permission:workout_plan.manage|workout_session.manage');
        Route::get('workout-books', [MemberWorkoutController::class, 'books'])
            ->middleware('permission:workout_template.view|workout_plan.view');
        Route::get('workout-books/recommended', [MemberWorkoutController::class, 'recommendedBooks'])
            ->middleware('permission:workout_template.view|workout_plan.view');
        Route::get('workout-exercises', [MemberWorkoutController::class, 'exercises'])
            ->middleware('permission:exercise.view|workout_plan.view|workout_session.manage');
        Route::post('workout-book-plans/{workoutTemplate}/adopt', [MemberWorkoutController::class, 'adoptPlan'])
            ->middleware('permission:workout_plan.manage|workout_session.manage');
        Route::post('workout-plans/{workoutPlan}/duplicate', [MemberWorkoutController::class, 'duplicatePlan'])
            ->middleware('permission:workout_plan.manage|workout_session.manage');
        Route::post('workout-sessions/start', [MemberWorkoutController::class, 'start'])
            ->middleware('permission:workout_session.manage');
        Route::get('workout-sessions/{workoutSession}', [MemberWorkoutController::class, 'showSession'])
            ->middleware('permission:workout_session.view|workout_session.manage');
        Route::post('workout-sessions/{workoutSession}/exercises', [MemberWorkoutController::class, 'addExercise'])
            ->middleware('permission:workout_session.manage');
        Route::post('workout-sessions/{workoutSession}/complete', [MemberWorkoutController::class, 'complete'])
            ->middleware('permission:workout_session.manage');
        Route::get('workout-history', [MemberWorkoutController::class, 'history'])
            ->middleware('permission:workout_session.view');
        Route::get('exercise-history/{exerciseId}', [MemberWorkoutController::class, 'exerciseHistory'])
            ->middleware('permission:workout_session.view|progress.view');
        Route::get('logbook-summary', [MemberWorkoutController::class, 'logbookSummary'])
            ->middleware('permission:workout_session.view|progress.view');
        Route::get('progress/summary', [MemberProgressController::class, 'summary'])
            ->middleware('permission:progress.view');
        Route::post('steps/sync', [MemberStepController::class, 'sync'])
            ->middleware('permission:progress.manage');
        Route::get('steps/today', [MemberStepController::class, 'today'])
            ->middleware('permission:progress.view');
        Route::get('steps/summary', [MemberStepController::class, 'summary'])
            ->middleware('permission:progress.view');
        Route::get('progress/weight-logs', [MemberProgressController::class, 'weightLogs'])
            ->middleware('permission:progress.view');
        Route::post('progress/weight-logs', [MemberProgressController::class, 'storeWeightLog'])
            ->middleware('permission:progress.manage');
        Route::get('progress/body-measurements', [MemberProgressController::class, 'bodyMeasurements'])
            ->middleware('permission:progress.view');
        Route::post('progress/body-measurements', [MemberProgressController::class, 'storeBodyMeasurement'])
            ->middleware('permission:progress.manage');
        Route::get('progress/photos', [MemberProgressController::class, 'photos'])
            ->middleware('permission:progress.view');
        Route::post('progress/photos', [MemberProgressController::class, 'storePhoto'])
            ->middleware('permission:progress.manage');
    });
