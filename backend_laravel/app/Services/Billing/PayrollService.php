<?php

namespace App\Services\Billing;

use App\Models\CommissionEarning;
use App\Models\CompensationProfile;
use App\Models\Gym;
use App\Models\PayrollPayment;
use App\Models\PayrollStatement;
use App\Models\User;
use App\Services\Gym\GymLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    public function __construct(private readonly GymLedgerService $gymLedgerService) {}

    public function generate(Gym $gym, Carbon $month): int
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $profiles = CompensationProfile::query()->where('gym_id', $gym->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $end))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start))->get();
        $commissionUsers = CommissionEarning::query()->where('gym_id', $gym->id)->where('status', 'earned')
            ->whereBetween('earned_at', [$start, $end])->distinct()->pluck('recipient_user_id');
        $userIds = $profiles->pluck('user_id')->merge($commissionUsers)->unique();

        foreach ($userIds as $userId) {
            $profile = $profiles->firstWhere('user_id', $userId);
            $commission = round((float) CommissionEarning::query()->where('gym_id', $gym->id)->where('recipient_user_id', $userId)
                ->where('status', 'earned')->whereBetween('earned_at', [$start, $end])->sum('amount'), 2);
            $statement = PayrollStatement::query()->firstOrNew(['gym_id' => $gym->id, 'user_id' => $userId, 'period_start' => $start->toDateString()]);
            if ($statement->exists && in_array($statement->status, ['paid', 'partially_paid'], true)) {
                continue;
            }
            $salary = round((float) ($profile?->monthly_salary ?? 0), 2);
            $statement->fill([
                'branch_id' => $profile?->branch_id,
                'period_end' => $end->toDateString(),
                'salary_amount' => $salary,
                'commission_amount' => $commission,
                'net_payable_amount' => max(0, round($salary + $commission + (float) ($statement->adjustment_amount ?? 0) - (float) ($statement->deduction_amount ?? 0), 2)),
                'status' => $statement->status ?: 'draft',
                'generated_at' => now(),
            ])->save();
        }

        return $userIds->count();
    }

    public function pay(PayrollStatement $statement, User $actor, array $data): PayrollPayment
    {
        return DB::transaction(function () use ($statement, $actor, $data): PayrollPayment {
            $statement = PayrollStatement::query()->lockForUpdate()->findOrFail($statement->id);
            $remaining = round((float) $statement->net_payable_amount - (float) $statement->paid_amount, 2);
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages(['amount' => ['The payout must be greater than zero and cannot exceed the remaining payable amount.']]);
            }
            $payment = PayrollPayment::query()->create([
                'payroll_statement_id' => $statement->id,
                'paid_by_user_id' => $actor->id,
                'amount' => $amount,
                'payment_mode' => $data['payment_mode'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);
            $statement->paid_amount = round((float) $statement->paid_amount + $amount, 2);
            $statement->status = $statement->paid_amount >= $statement->net_payable_amount ? 'paid' : 'partially_paid';
            $statement->approved_by_user_id ??= $actor->id;
            $statement->approved_at ??= now();
            $statement->save();
            $entry = $this->gymLedgerService->syncPayrollPaymentEntry($payment->fresh('statement.user'));
            $payment->update(['gym_ledger_entry_id' => $entry->id]);

            return $payment->fresh('ledgerEntry');
        });
    }
}
