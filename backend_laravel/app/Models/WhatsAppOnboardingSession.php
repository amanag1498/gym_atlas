<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppOnboardingSession extends Model
{
    protected $table = 'whatsapp_onboarding_sessions';

    protected $fillable = [
        'token_hash', 'gym_id', 'created_by_user_id', 'status', 'expires_at',
        'completed_at', 'last_error',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
