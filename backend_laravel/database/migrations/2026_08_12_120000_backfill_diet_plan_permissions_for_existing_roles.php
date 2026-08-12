<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewPermission = Permission::query()->firstOrCreate([
            'name' => PermissionName::DietPlansView->value,
            'guard_name' => 'sanctum',
        ]);
        $managePermission = Permission::query()->firstOrCreate([
            'name' => PermissionName::DietPlansManage->value,
            'guard_name' => 'sanctum',
        ]);

        $rolePermissions = [
            RoleName::PlatformAdmin->value => [$viewPermission, $managePermission],
            RoleName::GymOwner->value => [$viewPermission, $managePermission],
            RoleName::BranchManager->value => [$viewPermission, $managePermission],
            RoleName::GymStaff->value => [$viewPermission],
            RoleName::Trainer->value => [$viewPermission, $managePermission],
            RoleName::Member->value => [$viewPermission, $managePermission],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'sanctum')
                ->first();

            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Additive compatibility repair: do not revoke permissions that may now be in use.
    }
};
