<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Pending = 'pending_generation';
    case Generated = 'generated';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
