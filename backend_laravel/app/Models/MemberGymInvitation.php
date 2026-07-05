<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberGymInvitation extends Model
{
    protected $fillable = [
        'gym_id',
        'branch_id',
        'invited_user_id',
        'invited_email',
        'assigned_trainer_user_id',
        'invited_by_user_id',
        'status',
        'payload',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'responded_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function assignedTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_trainer_user_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
