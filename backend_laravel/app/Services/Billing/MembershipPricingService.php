<?php

namespace App\Services\Billing;

use App\Enums\DiscountType;
use App\Enums\PaymentStatus;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use Illuminate\Validation\ValidationException;

class MembershipPricingService
{
    public function buildMembershipPayload(MembershipPlan $plan, array $input): array
    {
        return $this->buildMembershipPayloadFromDefaults(
            defaultPlanPrice: (float) $plan->plan_price,
            defaultJoiningFee: (float) $plan->joining_fee,
            input: $input,
        );
    }

    public function recalculateMembership(MemberMembership $membership): MemberMembership
    {
        $payload = $this->buildMembershipPayloadFromDefaults(
            defaultPlanPrice: (float) $membership->default_plan_price,
            defaultJoiningFee: (float) $membership->default_joining_fee,
            input: [
            'custom_fee_enabled' => $membership->custom_fee_enabled,
            'custom_fee_amount' => $membership->custom_fee_amount,
            'discount_type' => $membership->discount_type,
            'discount_amount' => $membership->discount_amount,
            'custom_joining_fee' => $membership->custom_joining_fee,
            'joining_fee_waived' => $membership->joining_fee_waived,
            'partial_month_fee' => $membership->partial_month_fee,
            'pt_custom_fee' => $membership->pt_custom_fee,
            'amount_paid' => $membership->amount_paid,
            'payment_status' => $membership->payment_status,
            'custom_fee_reason' => $membership->custom_fee_reason,
            ],
        );

        $membership->fill($payload);

        return $membership;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function buildMembershipPayloadFromDefaults(float $defaultPlanPrice, float $defaultJoiningFee, array $input): array
    {
        $defaultPlanPrice = round($defaultPlanPrice, 2);
        $defaultJoiningFee = round($defaultJoiningFee, 2);
        $customFeeEnabled = (bool) ($input['custom_fee_enabled'] ?? false);
        $customFeeAmount = $customFeeEnabled
            ? round((float) ($input['custom_fee_amount'] ?? $defaultPlanPrice), 2)
            : 0.0;
        $effectivePlanPrice = $customFeeEnabled ? $customFeeAmount : $defaultPlanPrice;
        $discountType = $input['discount_type'] ?? DiscountType::None->value;
        $discountAmount = round((float) ($input['discount_amount'] ?? 0), 2);
        $customJoiningFee = array_key_exists('custom_joining_fee', $input)
            ? round((float) ($input['custom_joining_fee'] ?? 0), 2)
            : $defaultJoiningFee;
        $joiningFeeWaived = (bool) ($input['joining_fee_waived'] ?? false);
        $partialMonthFee = round((float) ($input['partial_month_fee'] ?? 0), 2);
        $ptCustomFee = round((float) ($input['pt_custom_fee'] ?? 0), 2);
        $effectiveJoiningFee = $joiningFeeWaived ? 0.0 : $customJoiningFee;

        $discountValue = match ($discountType) {
            DiscountType::Fixed->value => $discountAmount,
            DiscountType::Percentage->value => round($effectivePlanPrice * ($discountAmount / 100), 2),
            default => 0.0,
        };

        if ($discountValue > $effectivePlanPrice) {
            throw ValidationException::withMessages([
                'discount_amount' => ['The discount exceeds the effective plan price.'],
            ]);
        }

        $finalPayable = round(($effectivePlanPrice - $discountValue) + $effectiveJoiningFee + $partialMonthFee + $ptCustomFee, 2);

        if ($finalPayable < 0) {
            throw ValidationException::withMessages([
                'final_payable_amount' => ['The calculated payable amount cannot be negative.'],
            ]);
        }

        $amountPaid = round((float) ($input['amount_paid'] ?? 0), 2);
        $dueAmount = round($finalPayable - $amountPaid, 2);

        $dueDate = isset($input['due_date']) && $input['due_date'] !== null
            ? now()->parse((string) $input['due_date'])
            : null;

        if ($dueAmount < 0) {
            $paymentStatus = PaymentStatus::Overpaid->value;
        } elseif ($dueAmount == 0.0 && $amountPaid > 0) {
            $paymentStatus = PaymentStatus::Paid->value;
        } elseif ($amountPaid <= 0 && $dueDate?->isPast()) {
            $paymentStatus = PaymentStatus::Overdue->value;
        } elseif ($amountPaid > 0) {
            $paymentStatus = PaymentStatus::Partial->value;
        } else {
            $paymentStatus = PaymentStatus::Unpaid->value;
        }

        return [
            'default_plan_price' => $defaultPlanPrice,
            'default_joining_fee' => $defaultJoiningFee,
            'custom_fee_enabled' => $customFeeEnabled,
            'custom_fee_amount' => $customFeeAmount,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'custom_joining_fee' => $customJoiningFee,
            'joining_fee_waived' => $joiningFeeWaived,
            'partial_month_fee' => $partialMonthFee,
            'pt_custom_fee' => $ptCustomFee,
            'final_payable_amount' => $finalPayable,
            'amount_paid' => $amountPaid,
            'due_amount' => $dueAmount,
            'payment_status' => $paymentStatus,
            'custom_fee_reason' => $input['custom_fee_reason'] ?? null,
        ];
    }
}
