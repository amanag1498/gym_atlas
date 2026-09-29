<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollStatement extends Model
{
    protected $fillable = ['gym_id', 'branch_id', 'user_id', 'period_start', 'period_end', 'salary_amount', 'commission_amount', 'adjustment_amount', 'deduction_amount', 'net_payable_amount', 'paid_amount', 'status', 'notes', 'generated_at', 'approved_by_user_id', 'approved_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'salary_amount' => 'decimal:2', 'commission_amount' => 'decimal:2', 'adjustment_amount' => 'decimal:2', 'deduction_amount' => 'decimal:2', 'net_payable_amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'generated_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }
}
