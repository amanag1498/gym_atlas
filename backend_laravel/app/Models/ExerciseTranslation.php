<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseTranslation extends Model
{
    protected $fillable = [
        'exercise_id',
        'locale',
        'name',
        'instructions',
        'instruction_steps',
        'source',
        'review_status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'instruction_steps' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
