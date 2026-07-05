<?php

namespace App\Enums;

enum WorkoutSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
