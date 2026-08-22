<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationOutbox extends Model
{
    use HasFactory;

    protected $table = 'communication_outbox';

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'idempotency_key',
        'payload',
        'status',
        'attempt_count',
        'available_at',
        'locked_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
