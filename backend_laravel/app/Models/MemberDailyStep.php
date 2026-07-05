<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDailyStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gym_id',
        'step_date',
        'steps',
        'goal_steps',
        'calories_estimated',
        'distance_meters',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'step_date' => 'date',
            'steps' => 'integer',
            'goal_steps' => 'integer',
            'calories_estimated' => 'integer',
            'distance_meters' => 'integer',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
