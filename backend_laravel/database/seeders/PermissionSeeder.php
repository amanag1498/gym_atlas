<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::values() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $rolePermissions = [
            RoleName::PlatformAdmin->value => PermissionName::values(),
            RoleName::GymOwner->value => [
                PermissionName::GymDashboardView->value,
                PermissionName::GymsView->value,
                PermissionName::GymsManage->value,
                PermissionName::GymProfileManage->value,
                PermissionName::BranchesView->value,
                PermissionName::BranchesManage->value,
                PermissionName::StaffManage->value,
                PermissionName::TrainersView->value,
                PermissionName::TrainersManage->value,
                PermissionName::TrainerSelfManage->value,
                PermissionName::MembersView->value,
                PermissionName::MembersManage->value,
                PermissionName::MembershipPlansView->value,
                PermissionName::MembershipPlansManage->value,
                PermissionName::MembershipsView->value,
                PermissionName::MembershipsManage->value,
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsManage->value,
                PermissionName::EditCustomFee->value,
                PermissionName::AttendanceView->value,
                PermissionName::AttendanceManage->value,
                PermissionName::TrialRequestsView->value,
                PermissionName::TrialRequestsManage->value,
                PermissionName::ExercisesView->value,
                PermissionName::ExercisesManage->value,
                PermissionName::WorkoutTemplatesView->value,
                PermissionName::WorkoutTemplatesManage->value,
                PermissionName::WorkoutPlansView->value,
                PermissionName::WorkoutPlansManage->value,
                PermissionName::WorkoutSessionsView->value,
                PermissionName::WorkoutSessionsManage->value,
                PermissionName::ProgressView->value,
                PermissionName::ProgressManage->value,
                PermissionName::AnnouncementsView->value,
                PermissionName::AnnouncementsManage->value,
                PermissionName::NotificationsManage->value,
            ],
            RoleName::BranchManager->value => [
                PermissionName::GymDashboardView->value,
                PermissionName::GymsView->value,
                PermissionName::BranchesView->value,
                PermissionName::BranchesManage->value,
                PermissionName::StaffManage->value,
                PermissionName::TrainersView->value,
                PermissionName::TrainersManage->value,
                PermissionName::TrainerSelfManage->value,
                PermissionName::MembersView->value,
                PermissionName::MembersManage->value,
                PermissionName::MembershipPlansView->value,
                PermissionName::MembershipsView->value,
                PermissionName::MembershipsManage->value,
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsManage->value,
                PermissionName::EditCustomFee->value,
                PermissionName::AttendanceView->value,
                PermissionName::AttendanceManage->value,
                PermissionName::TrialRequestsView->value,
                PermissionName::TrialRequestsManage->value,
                PermissionName::ExercisesView->value,
                PermissionName::ExercisesManage->value,
                PermissionName::WorkoutTemplatesView->value,
                PermissionName::WorkoutTemplatesManage->value,
                PermissionName::WorkoutPlansView->value,
                PermissionName::WorkoutPlansManage->value,
                PermissionName::WorkoutSessionsView->value,
                PermissionName::WorkoutSessionsManage->value,
                PermissionName::ProgressView->value,
                PermissionName::ProgressManage->value,
                PermissionName::AnnouncementsView->value,
                PermissionName::AnnouncementsManage->value,
                PermissionName::NotificationsManage->value,
            ],
            RoleName::GymStaff->value => [
                PermissionName::GymDashboardView->value,
                PermissionName::GymsView->value,
                PermissionName::BranchesView->value,
                PermissionName::TrainersView->value,
                PermissionName::TrainerSelfManage->value,
                PermissionName::MembersView->value,
                PermissionName::MembershipPlansView->value,
                PermissionName::MembershipsView->value,
                PermissionName::PaymentsView->value,
                PermissionName::AttendanceView->value,
                PermissionName::AttendanceManage->value,
                PermissionName::ExercisesView->value,
                PermissionName::WorkoutTemplatesView->value,
                PermissionName::WorkoutPlansView->value,
                PermissionName::WorkoutSessionsView->value,
                PermissionName::ProgressView->value,
                PermissionName::AnnouncementsView->value,
            ],
            RoleName::Trainer->value => [
                PermissionName::TrainersView->value,
                PermissionName::TrainerSelfManage->value,
                PermissionName::MembersView->value,
                PermissionName::AttendanceView->value,
                PermissionName::ExercisesView->value,
                PermissionName::ExercisesManage->value,
                PermissionName::WorkoutTemplatesView->value,
                PermissionName::WorkoutTemplatesManage->value,
                PermissionName::WorkoutPlansView->value,
                PermissionName::WorkoutPlansManage->value,
                PermissionName::WorkoutSessionsView->value,
                PermissionName::ProgressView->value,
                PermissionName::ProgressManage->value,
                PermissionName::TrialRequestsView->value,
                PermissionName::TrialRequestsManage->value,
                PermissionName::NotificationsManage->value,
            ],
            RoleName::Member->value => [
                PermissionName::MembersView->value,
                PermissionName::ExercisesView->value,
                PermissionName::WorkoutTemplatesView->value,
                PermissionName::WorkoutPlansView->value,
                PermissionName::WorkoutPlansManage->value,
                PermissionName::WorkoutSessionsView->value,
                PermissionName::WorkoutSessionsManage->value,
                PermissionName::ProgressView->value,
                PermissionName::ProgressManage->value,
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'sanctum',
            ]);
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'sanctum')
                    ->whereIn('name', $permissions)
                    ->get(),
            );
        }
    }
}
