<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndependentTrainerMemberRelationship extends Model
{
    protected $fillable = [
        'trainer_user_id',
        'member_user_id',
        'invited_email',
        'is_current',
        'status',
        'sharing_permissions',
        'accepted_at',
        'declined_at',
        'revoked_at',
        'revoked_by_user_id',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'sharing_permissions' => 'array',
            'is_current' => 'boolean',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(IndependentTrainerMemberInvitation::class, 'relationship_id');
    }
}
