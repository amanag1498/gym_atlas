<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Frozen = 'frozen';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
