<?php

namespace App\Services\Billing;

use App\Enums\PaymentRecordStatus;
use App\Models\CommissionEarning;
use App\Models\MemberMembership;
use App\Models\MembershipCommissionAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function configure(MemberMembership $membership, array $rows): void
    {
        DB::transaction(function () use ($membership, $rows): void {
            $membership = MemberMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $extra = round((float) $membership->pt_custom_fee, 2);
            $prepared = collect($rows)
                ->filter(fn (array $row): bool => ! empty($row['recipient_user_id']) && (float) ($row['value'] ?? 0) > 0)
                ->values()
                ->map(function (array $row) use ($membership, $extra): array {
                    $recipientId = (int) $row['recipient_user_id'];
                    $belongsToGym = DB::table('gym_user')->where('gym_id', $membership->gym_id)->where('user_id', $recipientId)->exists();
                    if (! $belongsToGym) {
                        throw ValidationException::withMessages(['commissions' => ['Every commission recipient must belong to this gym.']]);
                    }

                    $calculationType = (string) ($row['calculation_type'] ?? 'percentage');
                    $value = round((float) $row['value'], 2);
                    if ($calculationType === 'percentage' && $value > 100) {
                        throw ValidationException::withMessages(['commissions' => ['A commission percentage cannot exceed 100%.']]);
                    }

                    $expected = $calculationType === 'fixed' ? $value : round($extra * $value / 100, 2);

                    return [
                        'gym_id' => $membership->gym_id,
                        'branch_id' => $membership->branch_id,
                        'member_membership_id' => $membership->id,
                        'recipient_user_id' => $recipientId,
                        'recipient_type' => $row['recipient_type'] ?? 'trainer',
                        'category' => $row['category'] ?? (($row['recipient_type'] ?? 'trainer') === 'staff' ? 'sales' : 'pt'),
                        'calculation_type' => $calculationType,
                        'value' => $value,
                        'recurrence' => $row['recurrence'] ?? 'one_time',
                        'commissionable_extra_amount' => $extra,
                        'expected_commission_amount' => $expected,
                        'status' => 'active',
                    ];
                });

            if ($prepared->isNotEmpty() && $extra <= 0) {
                throw ValidationException::withMessages(['commissions' => ['Enter a PT / commissionable extra amount before adding commission.']]);
            }

            if ($prepared->sum('expected_commission_amount') > $extra + 0.001) {
                throw ValidationException::withMessages(['commissions' => ['Trainer and staff commissions cannot exceed the PT / commissionable extra amount.']]);
            }

            $membership->commissionAllocations()->update(['status' => 'replaced']);
            foreach ($prepared as $allocation) {
                MembershipCommissionAllocation::query()->create($allocation);
            }

            $this->syncMembershipEarnings($membership);
        });
    }

    public function copyRecurring(MemberMembership $source, MemberMembership $target): void
    {
        $rows = $source->commissionAllocations()->where('status', 'active')->where('recurrence', 'recurring')->get();
        $extra = round((float) $target->pt_custom_fee, 2);

        foreach ($rows as $row) {
            $expected = $row->calculation_type === 'fixed'
                ? round((float) $row->value, 2)
                : round($extra * (float) $row->value / 100, 2);

            MembershipCommissionAllocation::query()->create([
                'gym_id' => $target->gym_id,
                'branch_id' => $target->branch_id,
                'member_membership_id' => $target->id,
                'recipient_user_id' => $row->recipient_user_id,
                'copied_from_allocation_id' => $row->id,
                'recipient_type' => $row->recipient_type,
                'category' => $row->category,
                'calculation_type' => $row->calculation_type,
                'value' => $row->value,
                'recurrence' => 'recurring',
                'commissionable_extra_amount' => $extra,
                'expected_commission_amount' => $expected,
                'status' => 'active',
            ]);
        }

        $this->syncMembershipEarnings($target);
    }

    public function syncMembershipEarnings(MemberMembership $membership): void
    {
        $membership->loadMissing('commissionAllocations');
        $allocations = $membership->commissionAllocations->where('status', 'active');
        $extra = round((float) $membership->pt_custom_fee, 2);
        $baseThreshold = max(0, round((float) $membership->final_payable_amount - $extra, 2));
        $payments = $membership->payments()->orderBy('paid_at')->orderBy('id')->get();
        $activeKeys = [];
        $cumulative = 0.0;
        $previousExtraCollected = 0.0;

        foreach ($payments as $payment) {
            if ($payment->status !== PaymentRecordStatus::Recorded->value) {
                continue;
            }

            $cumulative = round($cumulative + (float) $payment->amount, 2);
            $extraCollected = min($extra, max(0, round($cumulative - $baseThreshold, 2)));
            $priorExtraCollected = $previousExtraCollected;
            $delta = max(0, round($extraCollected - $priorExtraCollected, 2));

            foreach ($allocations as $allocation) {
                $cumulativeCommission = $allocation->calculation_type === 'fixed'
                    ? ($extra > 0 ? round((float) $allocation->expected_commission_amount * $extraCollected / $extra, 2) : 0)
                    : round($extraCollected * (float) $allocation->value / 100, 2);
                $priorCommission = $allocation->calculation_type === 'fixed'
                    ? ($extra > 0 ? round((float) $allocation->expected_commission_amount * $priorExtraCollected / $extra, 2) : 0)
                    : round($priorExtraCollected * (float) $allocation->value / 100, 2);
                $amount = round($cumulativeCommission - $priorCommission, 2);
                $key = $allocation->id.':'.$payment->id;
                $activeKeys[] = $key;
                CommissionEarning::query()->updateOrCreate(
                    ['membership_commission_allocation_id' => $allocation->id, 'payment_id' => $payment->id],
                    [
                        'gym_id' => $membership->gym_id,
                        'branch_id' => $membership->branch_id,
                        'recipient_user_id' => $allocation->recipient_user_id,
                        'commissionable_collected_amount' => $delta,
                        'amount' => $amount,
                        'status' => $amount > 0 ? 'earned' : 'pending',
                        'earned_at' => $payment->paid_at ?? $payment->created_at,
                        'reversed_at' => null,
                    ],
                );
            }

            $previousExtraCollected = $extraCollected;
        }

        CommissionEarning::query()
            ->whereHas('allocation', fn ($query) => $query->where('member_membership_id', $membership->id))
            ->get()
            ->each(function (CommissionEarning $earning) use ($activeKeys): void {
                if (! in_array($earning->membership_commission_allocation_id.':'.$earning->payment_id, $activeKeys, true)) {
                    $earning->update(['status' => 'reversed', 'reversed_at' => now()]);
                }
            });
    }
}
