<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatSafetyAction extends Model
{
    protected $fillable = [
        'actor_id',
        'target_id',
        'chat_message_id',
        'type',
        'reason',
        'details',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
