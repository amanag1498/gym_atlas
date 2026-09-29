<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEarning extends Model
{
    protected $fillable = ['gym_id', 'branch_id', 'membership_commission_allocation_id', 'payment_id', 'recipient_user_id', 'commissionable_collected_amount', 'amount', 'status', 'earned_at', 'reversed_at'];

    protected function casts(): array
    {
        return ['commissionable_collected_amount' => 'decimal:2', 'amount' => 'decimal:2', 'earned_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(MembershipCommissionAllocation::class, 'membership_commission_allocation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
