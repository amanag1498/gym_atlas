<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipCommissionAllocation extends Model
{
    protected $fillable = ['gym_id', 'branch_id', 'member_membership_id', 'recipient_user_id', 'copied_from_allocation_id', 'recipient_type', 'category', 'calculation_type', 'value', 'recurrence', 'commissionable_extra_amount', 'expected_commission_amount', 'status'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'commissionable_extra_amount' => 'decimal:2', 'expected_commission_amount' => 'decimal:2'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MemberMembership::class, 'member_membership_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(CommissionEarning::class);
    }
}
