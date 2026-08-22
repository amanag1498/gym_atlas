<?php

namespace App\Support;

final class CommunicationScope
{
    public static function key(?int $gymId, ?int $branchId = null): string
    {
        if ($gymId === null) {
            return 'platform';
        }

        return 'gym:'.$gymId.':branch:'.($branchId ?? 'all');
    }
}
