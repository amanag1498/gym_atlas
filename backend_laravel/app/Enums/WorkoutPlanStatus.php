<?php

namespace App\Enums;

enum WorkoutPlanStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Completed = 'completed';
}
