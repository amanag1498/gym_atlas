<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function updateActiveRole(User $authUser, User $targetUser): bool
    {
        return $authUser->is($targetUser);
    }
}
