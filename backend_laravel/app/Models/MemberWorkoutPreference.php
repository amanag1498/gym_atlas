<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberWorkoutPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'target_weight_kg', 'target_weight_updated_at', 'show_weight_goal',
        'timezone', 'scheduled_workout_reminder_enabled', 'reminder_minutes_before',
        'default_workout_time',
        'missed_workout_follow_up_enabled', 'streak_encouragement_enabled',
        'quiet_hours_start', 'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'target_weight_kg' => 'decimal:2',
            'target_weight_updated_at' => 'datetime',
            'show_weight_goal' => 'boolean',
            'scheduled_workout_reminder_enabled' => 'boolean',
            'missed_workout_follow_up_enabled' => 'boolean',
            'streak_encouragement_enabled' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
