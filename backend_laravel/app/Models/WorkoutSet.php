<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_session_exercise_id',
        'set_number',
        'reps',
        'duration_seconds',
        'distance_meters',
        'speed_kph',
        'pace_seconds_per_km',
        'weight',
        'rest_seconds',
        'effort_scale',
        'effort_value',
        'side',
        'notes',
        'is_completed',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'distance_meters' => 'decimal:2',
            'speed_kph' => 'decimal:2',
            'effort_value' => 'decimal:1',
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionExercise::class, 'workout_session_exercise_id');
    }
}
