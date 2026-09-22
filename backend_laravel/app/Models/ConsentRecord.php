<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'purpose',
        'policy_version',
        'source',
        'consented_at',
        'withdrawn_at',
        'ip_address',
        'user_agent',
        'notice_snapshot',
        'notice_hash',
        'notice_url',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
