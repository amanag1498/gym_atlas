<?php

namespace App\Services\Billing;

use App\Enums\PaymentMode;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MembershipEnrollmentService
{
    public function __construct(
        private readonly MembershipPricingService $membershipPricingService,
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{membership: MemberMembership, initial_payment: Payment|null}
     */
    public function enroll(MembershipPlan $plan, User $actor, array $input): array
    {
        $initialPaymentAmount = round((float) ($input['amount_paid'] ?? 0), 2);
        $startDate = Carbon::parse((string) $input['start_date']);
        $expiryDate = isset($input['expiry_date']) && $input['expiry_date'] !== null
            ? Carbon::parse((string) $input['expiry_date'])
            : $startDate->copy()->addDays($plan->duration_days);

        $this->assertNoOverlappingOperationalMembership(
            gymId: (int) $input['gym_id'],
            memberId: (int) $input['member_id'],
            startDate: $startDate->toDateString(),
            expiryDate: $expiryDate->toDateString(),
            ignoreMembershipId: isset($input['ignore_membership_id']) ? (int) $input['ignore_membership_id'] : null,
        );

        $pricing = $this->membershipPricingService->buildMembershipPayload($plan, [
            ...$input,
            'due_date' => $input['due_date'] ?? $expiryDate->toDateString(),
            'amount_paid' => 0,
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $input['gym_id'],
            'branch_id' => $input['branch_id'],
            'member_id' => $input['member_id'],
            'membership_plan_id' => $input['membership_plan_id'],
            'start_date' => $startDate->toDateString(),
            'expiry_date' => $expiryDate->toDateString(),
            'status' => $input['status'] ?? 'active',
            'due_date' => $input['due_date'] ?? $expiryDate->toDateString(),
            'approved_by_admin_id' => $actor->id,
            ...$pricing,
        ]);

        $initialPayment = null;

        if ($initialPaymentAmount > 0) {
            $initialPayment = $this->paymentService->recordPayment(
                $membership->fresh(['payments', 'membershipPlan']),
                $actor,
                [
                    'amount' => $initialPaymentAmount,
                    'payment_mode' => $input['initial_payment_mode'] ?? PaymentMode::Cash->value,
                    'paid_at' => $input['paid_at'] ?? now(),
                    'external_reference' => $input['external_reference'] ?? null,
                    'notes' => $input['payment_notes'] ?? 'Initial payment recorded during membership assignment.',
                    'allow_overpayment' => (bool) ($input['allow_overpayment'] ?? false),
                ],
            );

            $membership = $membership->fresh(['payments', 'membershipPlan']);
        }

        return [
            'membership' => $membership,
            'initial_payment' => $initialPayment,
        ];
    }

    private function assertNoOverlappingOperationalMembership(
        int $gymId,
        int $memberId,
        string $startDate,
        string $expiryDate,
        ?int $ignoreMembershipId = null,
    ): void {
        $overlap = MemberMembership::query()
            ->where('gym_id', $gymId)
            ->where('member_id', $memberId)
            ->operational()
            ->when($ignoreMembershipId, fn ($query) => $query->where('id', '!=', $ignoreMembershipId))
            ->overlappingCycle($startDate, $expiryDate)
            ->currentFirst()
            ->first();

        if (! $overlap) {
            return;
        }

        throw ValidationException::withMessages([
            'start_date' => ['This member already has an active membership cycle in the selected period. Use Change Plan only for the next cycle after '.optional($overlap->expiry_date)->format('d M Y').'.'],
        ]);
    }
}
