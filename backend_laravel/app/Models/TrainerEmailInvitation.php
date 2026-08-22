<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerEmailInvitation extends Model
{
    protected $fillable = ['token', 'gym_id', 'branch_id', 'invited_user_id', 'invited_by_user_id', 'invited_name', 'invited_email', 'status', 'payload', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }
}
