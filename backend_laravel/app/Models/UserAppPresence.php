<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAppPresence extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'app_role',
        'device_key',
        'platform',
        'device_name',
        'app_version',
        'first_seen_at',
        'last_seen_at',
        'last_push_success_at',
        'uninstall_suspected_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_push_success_at' => 'datetime',
            'uninstall_suspected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
