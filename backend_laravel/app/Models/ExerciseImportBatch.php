<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseImportBatch extends Model
{
    protected $fillable = [
        'source_key',
        'source_url',
        'source_commit',
        'license_code',
        'source_checksum',
        'status',
        'counts',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ExerciseSource::class);
    }
}
