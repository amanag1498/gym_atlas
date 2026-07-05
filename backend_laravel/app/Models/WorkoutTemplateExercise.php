<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutTemplateExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_template_day_id',
        'exercise_id',
        'sort_order',
        'sets',
        'reps',
        'target_weight',
        'rest_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplateDay::class, 'workout_template_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
