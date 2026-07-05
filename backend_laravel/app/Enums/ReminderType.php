<?php

namespace App\Enums;

enum ReminderType: string
{
    case MembershipExpiry = 'membership_expiry';
    case PaymentDue = 'payment_due';
    case CustomDue = 'custom_due';
    case AttendanceInactivity = 'attendance_inactivity';
    case WorkoutReminder = 'workout_reminder';

    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
