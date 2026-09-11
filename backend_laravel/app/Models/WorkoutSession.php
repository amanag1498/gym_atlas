<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'trainer_id',
        'workout_plan_id',
        'workout_plan_day_id',
        'workout_schedule_override_id',
        'plan_day_number',
        'plan_day_label',
        'day_selection_mode',
        'started_by_user_id',
        'session_date',
        'status',
        'started_at',
        'completed_at',
        'notes',
        'pre_workout_weight_kg',
        'total_volume',
        'completion_summary',
        'runtime_state',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_volume' => 'decimal:2',
            'pre_workout_weight_kg' => 'decimal:2',
            'completion_summary' => 'array',
            'runtime_state' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    public function planDay(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlanDay::class, 'workout_plan_day_id');
    }

    public function scheduleOverride(): BelongsTo
    {
        return $this->belongsTo(WorkoutScheduleOverride::class, 'workout_schedule_override_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class)->orderBy('sort_order');
    }
}
