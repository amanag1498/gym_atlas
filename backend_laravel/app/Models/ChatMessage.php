<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'room',
        'trainer_id',
        'member_id',
        'sender_id',
        'recipient_id',
        'body',
        'client_message_id',
        'metadata',
        'delivery_status',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
