<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_template_day_id',
        'exercise_id',
        'sort_order',
        'sets',
        'tracking_mode',
        'reps',
        'planned_duration_seconds',
        'planned_distance_meters',
        'planned_speed_kph',
        'planned_pace_seconds_per_km',
        'target_weight',
        'target_resistance',
        'target_machine_level',
        'is_per_side',
        'is_bodyweight',
        'rest_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
            'planned_distance_meters' => 'decimal:2',
            'planned_speed_kph' => 'decimal:2',
            'target_resistance' => 'decimal:2',
            'target_machine_level' => 'decimal:2',
            'is_per_side' => 'boolean',
            'is_bodyweight' => 'boolean',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplateDay::class, 'workout_template_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
