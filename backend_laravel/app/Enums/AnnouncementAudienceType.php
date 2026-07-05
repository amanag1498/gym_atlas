<?php

namespace App\Enums;

enum AnnouncementAudienceType: string
{
    case PlatformWide = 'platform_wide';
    case GymWide = 'gym_wide';
    case BranchSpecific = 'branch_specific';
    case SelectedMembers = 'selected_members';
    case Offer = 'offer';
    case TrainerAssignment = 'trainer_assignment';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
