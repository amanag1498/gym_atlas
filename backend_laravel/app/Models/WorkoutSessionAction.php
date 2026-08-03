<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSessionAction extends Model
{
    protected $fillable = [
        'workout_session_id',
        'user_id',
        'idempotency_key',
        'action',
        'resulting_revision',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
