<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutHistoryImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'created_by_user_id',
        'source_format',
        'source_filename',
        'source_file_hash',
        'status',
        'timezone',
        'summary',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(WorkoutHistoryImportRow::class);
    }
}
