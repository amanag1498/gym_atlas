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
        'tracking_mode',
        'sort_order',
        'planned_sets',
        'planned_reps',
        'planned_duration_seconds',
        'planned_distance_meters',
        'planned_speed_kph',
        'planned_pace_seconds_per_km',
        'target_weight',
        'target_resistance',
        'target_machine_level',
        'is_per_side',
        'is_bodyweight',
        'performed_status',
        'substituted_for_session_exercise_id',
        'rest_timer_seconds',
        'group_key',
        'group_type',
        'group_order',
        'group_rounds',
        'transition_seconds',
        'rest_after',
        'progression_policy',
        'progression_config',
        'progression_version',
        'progression_recommendation_id',
        'progression_explanation',
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
            'progression_config' => 'array',
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

    public function progressionRecommendation(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgressionRecommendation::class, 'progression_recommendation_id');
    }
}
