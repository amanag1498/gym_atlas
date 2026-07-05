<?php

namespace App\Services\Gym;

use App\Enums\PaymentRecordStatus;
use App\Models\Gym;
use App\Models\GymLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

class GymLedgerService
{
    public function syncPaymentEntry(Payment $payment): GymLedgerEntry
    {
        $payment->loadMissing(['member:id,name', 'membership.membershipPlan:id,name', 'branch:id,name']);

        $entry = GymLedgerEntry::query()->updateOrCreate(
            [
                'source_type' => Payment::class,
                'source_id' => $payment->id,
            ],
            [
                'gym_id' => $payment->gym_id,
                'branch_id' => $payment->branch_id,
                'created_by_user_id' => $payment->received_by_user_id ?: $payment->collected_by,
                'entry_type' => 'membership_collection',
                'direction' => 'inflow',
                'category' => 'member_payment',
                'title' => $this->paymentTitle($payment),
                'description' => $this->paymentDescription($payment),
                'reference' => $payment->receipt_number ?: $payment->external_reference,
                'payment_mode' => $payment->payment_mode,
                'amount' => $payment->amount,
                'status' => $payment->status === PaymentRecordStatus::Recorded->value ? 'posted' : 'reversed',
                'occurred_at' => $payment->paid_at ?: $payment->payment_date ?: $payment->created_at ?: now(),
                'metadata' => [
                    'payment_status' => $payment->payment_status,
                    'member_id' => $payment->member_id,
                    'member_name' => $payment->member?->name,
                    'membership_id' => $payment->member_membership_id,
                    'membership_plan' => $payment->membership?->membershipPlan?->name,
                ],
            ],
        );

        return $entry->fresh(['branch', 'creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManualEntry(Gym $gym, User $actor, array $data): GymLedgerEntry
    {
        return GymLedgerEntry::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $data['branch_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'source_type' => 'manual',
            'source_id' => null,
            'entry_type' => $data['entry_type'],
            'direction' => $this->directionForManualType($data['entry_type'], $data['adjustment_direction'] ?? null),
            'category' => Str::of((string) $data['category'])->trim()->snake()->value(),
            'title' => trim((string) $data['title']),
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'payment_mode' => $data['payment_mode'] ?? null,
            'amount' => round((float) $data['amount'], 2),
            'status' => 'posted',
            'occurred_at' => $data['occurred_at'],
            'metadata' => [
                'entry_origin' => 'manual',
                'category_label' => Str::of((string) $data['category'])->replace('_', ' ')->title()->value(),
            ],
        ]);
    }

    public function signedAmount(GymLedgerEntry $entry): float
    {
        if ($entry->status !== 'posted') {
            return 0.0;
        }

        $amount = round((float) $entry->amount, 2);

        return $entry->direction === 'outflow' ? $amount * -1 : $amount;
    }

    public function reverseManualEntry(GymLedgerEntry $entry, User $actor, string $reason): GymLedgerEntry
    {
        abort_unless($entry->source_type === 'manual', 422, 'Only manual ledger entries can be reversed here.');
        abort_unless($entry->status === 'posted', 422, 'This ledger entry has already been reversed.');

        $metadata = is_array($entry->metadata) ? $entry->metadata : [];
        $metadata['reversed_by_user_id'] = $actor->id;
        $metadata['reversed_by_name'] = $actor->name;
        $metadata['reversal_reason'] = $reason;
        $metadata['reversed_at'] = now()->toIso8601String();

        $entry->forceFill([
            'status' => 'reversed',
            'description' => trim(collect([$entry->description, 'Reversed: '.$reason])->filter()->implode(' | ')),
            'metadata' => $metadata,
        ])->save();

        return $entry->fresh(['branch', 'creator']);
    }

    private function directionForManualType(string $entryType, ?string $adjustmentDirection): string
    {
        return match ($entryType) {
            'expense', 'refund' => 'outflow',
            'other_income' => 'inflow',
            'adjustment' => $adjustmentDirection === 'outflow' ? 'outflow' : 'inflow',
            default => 'outflow',
        };
    }

    private function paymentTitle(Payment $payment): string
    {
        $memberName = $payment->member?->name ?? 'Member';

        return $memberName.' collection';
    }

    private function paymentDescription(Payment $payment): string
    {
        $planName = $payment->membership?->membershipPlan?->name ?? 'Membership';
        $mode = strtoupper((string) ($payment->payment_mode ?: 'cash'));
        $note = $payment->notes ? ' • '.trim((string) $payment->notes) : '';

        return $planName.' payment captured via '.$mode.$note;
    }
}
