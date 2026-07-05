<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'trainer_id',
        'created_by_user_id',
        'source_workout_book_id',
        'plan_origin',
        'is_member_editable',
        'workout_template_id',
        'name',
        'goal',
        'difficulty',
        'duration_weeks',
        'estimated_session_minutes',
        'equipment_profile',
        'weekly_schedule',
        'notes',
        'status',
        'assigned_at',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'weekly_schedule' => 'array',
            'is_member_editable' => 'boolean',
            'assigned_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id');
    }

    public function sourceWorkoutBook(): BelongsTo
    {
        return $this->belongsTo(WorkoutBook::class, 'source_workout_book_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkoutPlanDay::class)->orderBy('day_number');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorkoutSession::class);
    }
}
