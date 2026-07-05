<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'workout_book_id',
        'created_by_user_id',
        'name',
        'goal',
        'difficulty',
        'program_type',
        'equipment_profile',
        'duration_weeks',
        'estimated_session_minutes',
        'weekly_schedule',
        'notes',
        'status',
        'is_public_catalog',
    ];

    protected function casts(): array
    {
        return [
            'weekly_schedule' => 'array',
            'is_public_catalog' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function workoutBook(): BelongsTo
    {
        return $this->belongsTo(WorkoutBook::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkoutTemplateDay::class)->orderBy('day_number');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }
}
