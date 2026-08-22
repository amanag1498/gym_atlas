<?php

namespace App\Models;

use App\Support\CommunicationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConsent extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_consents';

    protected $fillable = [
        'user_id', 'gym_id', 'scope_key', 'purpose', 'status', 'phone_e164', 'source',
        'wording_version', 'evidence', 'granted_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'granted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $consent): void {
            $consent->scope_key ??= CommunicationScope::key($consent->gym_id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
