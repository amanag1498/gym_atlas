<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutHistoryImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_history_import_batch_id',
        'row_number',
        'row_hash',
        'status',
        'matched_exercise_id',
        'workout_session_id',
        'raw_payload',
        'normalized_payload',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WorkoutHistoryImportBatch::class, 'workout_history_import_batch_id');
    }

    public function matchedExercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'matched_exercise_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
