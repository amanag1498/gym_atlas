<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversation extends Model
{
    protected $fillable = [
        'room',
        'trainer_id',
        'member_id',
        'last_message_id',
        'last_message_body',
        'last_sender_id',
        'last_message_at',
        'trainer_unread_count',
        'member_unread_count',
        'trainer_read_at',
        'member_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'trainer_read_at' => 'datetime',
            'member_read_at' => 'datetime',
            'trainer_unread_count' => 'integer',
            'member_unread_count' => 'integer',
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

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function lastSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_sender_id');
    }
}
