<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case Upi = 'upi';
    case Card = 'card';
    case Bank = 'bank';

    public static function values(): array
    {
        return array_map(
            static fn (self $mode): string => $mode->value,
            self::cases(),
        );
    }
}
