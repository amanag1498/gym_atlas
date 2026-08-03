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
        'weight',
        'rest_seconds',
        'notes',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'is_completed' => 'boolean',
        ];
    }

    public function sessionExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionExercise::class, 'workout_session_exercise_id');
    }
}
