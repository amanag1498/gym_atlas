<?php

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Exceptions\FirebaseTokenVerificationException;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Authorization\ActiveRoleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class FirebaseAuthService
{
    public function __construct(
        private readonly FirebaseTokenVerifier $firebaseTokenVerifier,
        private readonly ActiveRoleManager $activeRoleManager,
    ) {
    }

    /**
     * @return array{token: string, user: \App\Models\User}
     */
    public function authenticate(string $idToken, string $deviceName): array
    {
        $user = $this->resolveUser($idToken, 'auth.firebase.login');

        return [
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user->fresh(['roles', 'permissions']),
        ];
    }

    public function authenticateForWeb(string $idToken): User
    {
        return $this->resolveUser($idToken, 'auth.firebase.web.login', false);
    }

    private function resolveUser(string $idToken, string $event, bool $allowAutoProvision = true): User
    {
        try {
            $payload = $this->firebaseTokenVerifier->verify($idToken);
        } catch (FirebaseTokenVerificationException $exception) {
            throw ValidationException::withMessages([
                'id_token' => [$exception->getMessage()],
            ]);
        }

        return DB::transaction(function () use ($payload, $event, $allowAutoProvision): User {
            $user = User::query()->firstWhere('firebase_uid', $payload['sub'])
                ?? User::query()->firstWhere('email', $payload['email']);

            if (! $user) {
                if (! $allowAutoProvision) {
                    throw ValidationException::withMessages([
                        'email' => ['No web panel account is linked to this Google email. Ask an admin to create or assign your gym panel account first.'],
                    ]);
                }

                $user = new User();
            }

            $user->fill([
                'name' => $payload['name'] ?? explode('@', $payload['email'])[0],
                'email' => $payload['email'],
                'firebase_uid' => $payload['sub'],
                'avatar' => $payload['picture'] ?? $user->avatar,
                'auth_provider' => 'firebase_google',
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
                'event' => $event,
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'context' => [
                    'auth_provider' => 'firebase_google',
                ],
                'occurred_at' => now(),
            ]);

            return $user->fresh(['roles', 'permissions']);
        });
    }
}
