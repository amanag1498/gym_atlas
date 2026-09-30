<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayment extends Model
{
    protected $fillable = ['payroll_statement_id', 'paid_by_user_id', 'gym_ledger_entry_id', 'amount', 'payment_mode', 'reference', 'notes', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(PayrollStatement::class, 'payroll_statement_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(GymLedgerEntry::class, 'gym_ledger_entry_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
