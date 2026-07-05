<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_membership_id',
        'member_id',
        'received_by_user_id',
        'collected_by',
        'amount',
        'payment_mode',
        'status',
        'payment_status',
        'external_reference',
        'receipt_number',
        'notes',
        'paid_at',
        'payment_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_date' => 'datetime',
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

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MemberMembership::class, 'member_membership_id');
    }

    public function memberMembership(): BelongsTo
    {
        return $this->membership();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
