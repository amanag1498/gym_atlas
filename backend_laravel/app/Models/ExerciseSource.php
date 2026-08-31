<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseSource extends Model
{
    protected $fillable = [
        'exercise_id',
        'exercise_import_batch_id',
        'source_key',
        'source_external_id',
        'source_url',
        'source_commit',
        'license_code',
        'content_checksum',
        'imported_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ExerciseImportBatch::class, 'exercise_import_batch_id');
    }
}
