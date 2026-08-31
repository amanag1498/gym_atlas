<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMedia extends Model
{
    protected $table = 'exercise_media';

    protected $fillable = [
        'exercise_id',
        'kind',
        'source_type',
        'remote_url',
        'storage_disk',
        'storage_path',
        'mime_type',
        'width',
        'height',
        'duration_ms',
        'checksum',
        'license_code',
        'attribution_text',
        'license_evidence_reference',
        'status',
        'sort_order',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
