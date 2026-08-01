<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndependentTrainerMemberInvitation extends Model
{
    protected $fillable = [
        'relationship_id',
        'token',
        'trainer_user_id',
        'invited_user_id',
        'invited_name',
        'invited_email',
        'invited_by_user_id',
        'status',
        'payload',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(IndependentTrainerMemberRelationship::class, 'relationship_id');
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
