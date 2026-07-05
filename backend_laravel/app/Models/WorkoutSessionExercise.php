<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSessionExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_session_id',
        'workout_plan_exercise_id',
        'exercise_id',
        'sort_order',
        'planned_sets',
        'planned_reps',
        'target_weight',
        'rest_timer_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function planExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlanExercise::class, 'workout_plan_exercise_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('set_number');
    }
}
