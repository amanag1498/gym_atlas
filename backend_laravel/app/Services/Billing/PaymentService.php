<?php

namespace App\Services\Billing;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiptStatus;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Gym\GymLedgerService;
use App\Services\Notification\ReminderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly MembershipPricingService $membershipPricingService,
        private readonly ReminderService $reminderService,
        private readonly GymLedgerService $gymLedgerService,
    ) {
    }

    public function recordPayment(MemberMembership $membership, User $actor, array $input): Payment
    {
        if ($membership->status === MembershipStatus::Cancelled->value) {
            throw ValidationException::withMessages([
                'membership' => ['Payments cannot be recorded for a cancelled membership.'],
            ]);
        }

        $amount = round((float) $input['amount'], 2);
        $dueAmount = round((float) $membership->due_amount, 2);
        $allowOverpayment = (bool) ($input['allow_overpayment'] ?? false);

        if (! $allowOverpayment && $amount > max($dueAmount, 0.0)) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount cannot exceed the current due amount unless overpayment is explicitly allowed.'],
            ]);
        }

        return DB::transaction(function () use ($membership, $actor, $input, $amount): Payment {
            $paymentTimestamp = $input['payment_date'] ?? $input['paid_at'] ?? now();

            $payment = Payment::query()->create([
                'gym_id' => $membership->gym_id,
                'branch_id' => $membership->branch_id,
                'member_membership_id' => $membership->id,
                'member_id' => $membership->member_id,
                'received_by_user_id' => $actor->id,
                'collected_by' => $actor->id,
                'amount' => $amount,
                'payment_mode' => $input['payment_mode'],
                'status' => PaymentRecordStatus::Recorded->value,
                'payment_status' => 'paid',
                'external_reference' => $input['external_reference'] ?? null,
                'notes' => $input['notes'] ?? null,
                'paid_at' => $paymentTimestamp,
                'payment_date' => $paymentTimestamp,
            ]);

            $receipt = PaymentReceipt::query()->create([
                'payment_id' => $payment->id,
                'receipt_number' => sprintf('RCT-%06d', $payment->id),
                'status' => ReceiptStatus::Pending->value,
            ]);

            $payment->forceFill(['receipt_number' => $receipt->receipt_number])->save();

            $this->syncMembershipBalance($membership->fresh(['payments', 'membershipPlan']));

            $freshPayment = $payment->fresh(['receipt', 'member', 'membership.membershipPlan', 'branch']);
            $this->gymLedgerService->syncPaymentEntry($freshPayment);

            return $freshPayment;
        });
    }

    public function markPaid(MemberMembership $membership, User $actor, ?string $paymentMode, ?string $notes, mixed $paidAt): Payment
    {
        $dueAmount = round((float) $membership->due_amount, 2);

        if ($dueAmount <= 0) {
            throw ValidationException::withMessages([
                'membership' => ['This membership has no outstanding due amount to settle.'],
            ]);
        }

        return $this->recordPayment($membership, $actor, [
            'amount' => $dueAmount,
            'payment_mode' => $paymentMode ?: PaymentMode::Cash->value,
            'notes' => $notes,
            'paid_at' => $paidAt ?: now(),
        ]);
    }

    public function markUnpaid(MemberMembership $membership, ?string $reason): MemberMembership
    {
        return DB::transaction(function () use ($membership, $reason): MemberMembership {
            $membership->payments()
                ->where('status', PaymentRecordStatus::Recorded->value)
                ->each(function (Payment $payment) use ($reason): void {
                    $payment->update([
                        'status' => PaymentRecordStatus::Reversed->value,
                        'payment_status' => 'refunded',
                        'notes' => trim(($payment->notes ? $payment->notes.' | ' : '').($reason ?: 'Marked unpaid'), ' |'),
                    ]);
                    $this->gymLedgerService->syncPaymentEntry($payment->fresh(['member', 'membership.membershipPlan', 'branch']));
                });

            $this->syncMembershipBalance($membership->fresh(['payments', 'membershipPlan']));

            return $membership->fresh(['payments.receipt', 'membershipPlan']);
        });
    }

    public function reversePayment(Payment $payment, ?string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            if ($payment->status === PaymentRecordStatus::Reversed->value) {
                throw ValidationException::withMessages([
                    'payment' => ['This payment has already been reversed.'],
                ]);
            }

            $payment->forceFill([
                'status' => PaymentRecordStatus::Reversed->value,
                'payment_status' => 'refunded',
                'notes' => trim(($payment->notes ? $payment->notes.' | ' : '').($reason ?: 'Payment reversed'), ' |'),
            ])->save();

            $membership = $payment->membership()->with(['payments', 'membershipPlan'])->first();

            if ($membership) {
                $this->syncMembershipBalance($membership);
            }

            $freshPayment = $payment->fresh(['receipt', 'membership.membershipPlan', 'member', 'branch']);
            $this->gymLedgerService->syncPaymentEntry($freshPayment);

            return $freshPayment;
        });
    }

    public function syncMembershipBalance(MemberMembership $membership): MemberMembership
    {
        $amountPaid = round((float) $membership->payments()->where('status', PaymentRecordStatus::Recorded->value)->sum('amount'), 2);
        $membership->amount_paid = $amountPaid;
        $this->membershipPricingService->recalculateMembership($membership);

        if ((float) $membership->due_amount < 0) {
            $membership->payment_status = PaymentStatus::Overpaid->value;
        } elseif ((float) $membership->due_amount == 0.0 && (float) $membership->amount_paid > 0) {
            $membership->payment_status = PaymentStatus::Paid->value;
        } elseif ((float) $membership->amount_paid > 0) {
            $membership->payment_status = PaymentStatus::Partial->value;
        } elseif ($membership->due_date && $membership->due_date->isPast()) {
            $membership->payment_status = PaymentStatus::Overdue->value;
        } else {
            $membership->payment_status = PaymentStatus::Unpaid->value;
        }

        $membership->save();
        $this->reminderService->syncMembershipReminders($membership->fresh('membershipPlan'));

        return $membership;
    }
}
