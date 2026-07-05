<?php

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Exceptions\GoogleTokenVerificationException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Authorization\ActiveRoleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class GoogleAuthService
{
    public function __construct(
        private readonly GoogleTokenVerifier $googleTokenVerifier,
        private readonly ActiveRoleManager $activeRoleManager,
    ) {
    }

    /**
     * @return array{token: string, user: \App\Models\User}
     */
    public function authenticate(string $idToken, string $deviceName): array
    {
        try {
            $payload = $this->googleTokenVerifier->verify($idToken);
        } catch (GoogleTokenVerificationException $exception) {
            throw ValidationException::withMessages([
                'id_token' => [$exception->getMessage()],
            ]);
        }

        return DB::transaction(function () use ($payload, $deviceName): array {
            $user = User::query()->firstWhere('google_id', $payload['sub'])
                ?? User::query()->firstWhere('email', $payload['email']);

            if (! $user) {
                $user = new User();
            }

            $user->fill([
                'name' => $payload['name'] ?? explode('@', $payload['email'])[0],
                'email' => $payload['email'],
                'google_id' => $payload['sub'],
                'avatar' => $payload['picture'] ?? $user->avatar,
                'auth_provider' => 'google',
                'is_active' => $user->exists ? $user->is_active : true,
                'last_login_at' => now(),
            ]);
            $user->email_verified_at = now();
            $user->save();

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['This account is inactive. Please contact support.'],
                ]);
            }

            if ($user->roles()->doesntExist()) {
                $user->assignRole(RoleName::Member->value);
            }

            if (in_array($user->email, config('gym.platform_admin_emails', []), true)) {
                $user->assignRole(Role::findOrCreate(RoleName::PlatformAdmin->value, 'sanctum'));
            }

            $this->activeRoleManager->ensureValidActiveRole($user);

            ActivityLog::query()->create([
                'actor_user_id' => $user->id,
                'event' => 'auth.google.login',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'context' => [
                    'auth_provider' => 'google',
                ],
                'occurred_at' => now(),
            ]);

            return [
                'token' => $user->createToken($deviceName)->plainTextToken,
                'user' => $user->fresh(['roles', 'permissions']),
            ];
        });
    }
}
