<?php

namespace App\Enums;

enum RoleName: string
{
    case PlatformAdmin = 'platform_admin';
    case GymOwner = 'gym_owner';
    case BranchManager = 'branch_manager';
    case GymStaff = 'gym_staff';
    case Trainer = 'trainer';
    case Member = 'member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
