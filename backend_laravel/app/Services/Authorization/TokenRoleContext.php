<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class TokenRoleContext
{
    private const ABILITY_PREFIX = 'role:';

    public function apply(User $user): bool
    {
        $roleAbilities = $this->roleAbilities($user);

        if ($roleAbilities === []) {
            return true;
        }

        if (count($roleAbilities) !== 1) {
            return false;
        }

        $role = substr($roleAbilities[0], strlen(self::ABILITY_PREFIX));

        if (! in_array($role, RoleName::values(), true) || ! $user->hasRole($role)) {
            return false;
        }

        // Keep the role scoped to this authenticated request. Persisting it on
        // users would make Member and Trainer sessions overwrite each other.
        $user->setAttribute('active_role', $role);

        return true;
    }

    /**
     * @return list<string>
     */
    private function roleAbilities(User $user): array
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! is_array($token->abilities)) {
            return [];
        }

        $abilities = $token->abilities;

        $roleAbilities = array_values(array_filter(
            $abilities,
            static fn (mixed $ability): bool => is_string($ability)
                && str_starts_with($ability, self::ABILITY_PREFIX),
        ));

        if ($roleAbilities !== []) {
            return $roleAbilities;
        }

        // Tokens issued by earlier mobile releases used the default wildcard
        // ability. Their stable device names let deployment fix active sessions
        // without forcing every Member and Trainer user to sign in again.
        return match ($token->name) {
            'flutter_member_app' => ['role:'.RoleName::Member->value],
            'flutter_trainer_app' => ['role:'.RoleName::Trainer->value],
            default => [],
        };
    }
}
