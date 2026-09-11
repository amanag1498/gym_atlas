<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutProgressionRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'trainer_id',
        'workout_plan_id',
        'workout_plan_exercise_id',
        'exercise_id',
        'source_workout_session_id',
        'policy',
        'algorithm_version',
        'action',
        'status',
        'current_prescription',
        'recommended_prescription',
        'decision_inputs',
        'explanation',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'current_prescription' => 'array',
            'recommended_prescription' => 'array',
            'decision_inputs' => 'array',
            'reviewed_at' => 'datetime',
        ];
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

    public function planExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlanExercise::class, 'workout_plan_exercise_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function sourceSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'source_workout_session_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
