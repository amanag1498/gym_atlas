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

        $trainerRole = Role::query()
            ->where('name', RoleName::Trainer->value)
            ->where('guard_name', 'sanctum')
            ->first();

        if (! $trainerRole) {
            return;
        }

        $permissions = collect([
            PermissionName::DietPlansView->value,
            PermissionName::DietPlansManage->value,
        ])->map(fn (string $name) => Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => 'sanctum',
        ]));

        $trainerRole->givePermissionTo($permissions->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Do not revoke permissions that may have been granted independently.
    }
};
