<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Authorization\ActiveRoleManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $activeRoleManager = app(ActiveRoleManager::class);

        foreach (config('gym.platform_admin_emails', []) as $email) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => str($email)->before('@')->replace(['.', '_', '-'], ' ')->title()->toString(),
                    'auth_provider' => 'google',
                    'email_verified_at' => now(),
                    'password' => Hash::make((string) config('gym.seeded_admin_password')),
                ],
            );

            $user->assignRole(RoleName::PlatformAdmin->value);
            $activeRoleManager->ensureValidActiveRole($user);
        }
    }
}
