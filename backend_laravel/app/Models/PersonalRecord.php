<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'exercise_id',
        'workout_session_id',
        'best_weight',
        'best_reps',
        'best_volume',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'best_weight' => 'decimal:2',
            'best_volume' => 'decimal:2',
            'achieved_at' => 'datetime',
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

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }
}
