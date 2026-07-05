<?php

namespace App\Enums;

enum AttendanceCheckInMethod: string
{
    case Biometric = 'biometric';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_map(
            static fn (self $method): string => $method->value,
            self::cases(),
        );
    }
}
