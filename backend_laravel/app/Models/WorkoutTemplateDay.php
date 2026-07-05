<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutTemplateDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_template_id',
        'day_number',
        'label',
        'focus',
        'notes',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkoutTemplate::class, 'workout_template_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutTemplateExercise::class)->orderBy('sort_order');
    }
}
