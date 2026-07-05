<?php

namespace App\Enums;

enum PaymentRecordStatus: string
{
    case Recorded = 'recorded';
    case Reversed = 'reversed';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
