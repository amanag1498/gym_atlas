<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'app_role',
        'device_name',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDeliverable(Builder $query): Builder
    {
        $staleDays = max(1, (int) config('services.firebase.token_stale_days', 60));

        return $query->where(fn (Builder $token) => $token
            ->whereNull('last_seen_at')
            ->orWhere('last_seen_at', '>=', now()->subDays($staleDays)));
    }
}
