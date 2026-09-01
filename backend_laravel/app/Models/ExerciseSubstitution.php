<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSubstitution extends Model
{
    protected $fillable = [
        'exercise_id', 'substitute_exercise_id', 'reason', 'priority',
        'requires_trainer_approval', 'is_active', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['requires_trainer_approval' => 'boolean', 'is_active' => 'boolean'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'substitute_exercise_id');
    }
}
