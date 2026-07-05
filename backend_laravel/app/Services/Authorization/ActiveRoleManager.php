<?php

namespace App\Services\Authorization;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ActiveRoleManager
{
    public function ensureValidActiveRole(User $user): User
    {
        $roleNames = $user->getRoleNames()->values()->all();

        if (empty($roleNames)) {
            if ($user->active_role !== null) {
                $user->forceFill(['active_role' => null])->save();
            }

            return $user;
        }

        if ($user->active_role && in_array($user->active_role, $roleNames, true)) {
            return $user;
        }

        $preferredRole = collect(RoleName::values())
            ->first(fn (string $role): bool => in_array($role, $roleNames, true), $roleNames[0]);

        $user->forceFill(['active_role' => $preferredRole])->save();

        return $user;
    }

    public function setActiveRole(User $user, string $role): User
    {
        if (! $user->hasRole($role)) {
            throw ValidationException::withMessages([
                'active_role' => ['The requested role is not assigned to this user.'],
            ]);
        }

        $user->forceFill(['active_role' => $role])->save();

        return $user;
    }
}
