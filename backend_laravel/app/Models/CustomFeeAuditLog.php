<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFeeAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'gym_id',
        'member_id',
        'member_membership_id',
        'old_values',
        'new_values',
        'changed_by',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MemberMembership::class, 'member_membership_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
