<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gym_id',
        'branch_id',
        'member_membership_id',
        'type',
        'title',
        'body',
        'payload',
        'scheduled_for',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MemberMembership::class, 'member_membership_id');
    }
}
