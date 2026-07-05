<?php

namespace App\Enums;

enum DiscountType: string
{
    case None = 'none';
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
