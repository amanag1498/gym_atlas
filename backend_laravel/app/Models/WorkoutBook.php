<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by_user_id',
        'name',
        'slug',
        'audience',
        'goal',
        'difficulty',
        'program_type',
        'equipment_profile',
        'days_per_week',
        'duration_weeks',
        'estimated_session_minutes',
        'description',
        'coach_notes',
        'is_featured',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WorkoutTemplate::class)->orderBy('id');
    }
}
