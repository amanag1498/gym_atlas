<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'name',
        'muscle_group',
        'secondary_muscles',
        'equipment',
        'difficulty',
        'instructions',
        'image_url',
        'video_url',
        'is_global',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'secondary_muscles' => 'array',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
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

    public function templateExercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class);
    }

    public function planExercises(): HasMany
    {
        return $this->hasMany(WorkoutPlanExercise::class);
    }

    public function sessionExercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }
}
