<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAlias extends Model
{
    protected $fillable = [
        'exercise_id',
        'alias',
        'normalized_alias',
        'locale',
        'source',
        'review_status',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
