<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (['event.view', 'event.manage', 'event_booking.view', 'event.check_in'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'sanctum'],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $roles = DB::table('roles')->whereIn('name', ['platform_admin', 'gym_owner', 'branch_manager', 'gym_staff', 'trainer', 'member'])->get();
        $permissions = DB::table('permissions')->whereIn('name', ['event.view', 'event.manage', 'event_booking.view', 'event.check_in'])->get()->keyBy('name');
        foreach ($roles as $role) {
            $names = match ($role->name) {
                'platform_admin', 'gym_owner', 'branch_manager' => ['event.view', 'event.manage', 'event_booking.view', 'event.check_in'],
                'gym_staff' => ['event.view', 'event_booking.view', 'event.check_in'],
                'trainer' => ['event.view', 'event_booking.view', 'event.check_in'],
                default => ['event.view'],
            };
            foreach ($names as $name) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permissions[$name]->id,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', ['event.view', 'event.manage', 'event_booking.view', 'event.check_in'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
