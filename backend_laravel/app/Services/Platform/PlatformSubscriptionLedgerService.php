<?php

namespace App\Services\Platform;

use App\Models\GymPlatformSubscription;
use App\Models\GymPlatformSubscriptionInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformSubscriptionLedgerService
{
    public function syncInvoiceStatuses(?CarbonImmutable $today = null): void
    {
        $today ??= now()->startOfDay()->toImmutable();

        GymPlatformSubscriptionInvoice::query()
            ->where('status', 'due')
            ->whereDate('due_at', '<', $today->toDateString())
            ->update(['status' => 'overdue']);
    }

    public function issueInitialInvoice(GymPlatformSubscription $subscription, ?int $actorUserId = null): ?GymPlatformSubscriptionInvoice
    {
        $subscription->loadMissing(['gym', 'plan', 'invoices']);

        if ($subscription->invoices()->exists()) {
            return $subscription->invoices()->latest('id')->first();
        }

        [$periodStartsAt, $periodEndsAt] = $this->resolveCurrentWindow($subscription);

        return $this->createInvoiceForWindow(
            subscription: $subscription,
            periodStartsAt: $periodStartsAt,
            periodEndsAt: $periodEndsAt,
            actorUserId: $actorUserId,
            includeSetupFee: true,
        );
    }

    public function issueRenewalInvoice(GymPlatformSubscription $subscription, string $periodStartsAt, string $periodEndsAt, ?int $actorUserId = null): GymPlatformSubscriptionInvoice
    {
        $subscription->loadMissing(['gym', 'plan']);

        return $this->createInvoiceForWindow(
            subscription: $subscription,
            periodStartsAt: $periodStartsAt,
            periodEndsAt: $periodEndsAt,
            actorUserId: $actorUserId,
            includeSetupFee: false,
        );
    }

    public function markPaid(
        GymPlatformSubscriptionInvoice $invoice,
        ?int $actorUserId = null,
        ?string $paymentReference = null,
        ?string $paymentNotes = null,
        ?string $paidAt = null,
    ): GymPlatformSubscriptionInvoice {
        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => $paidAt ? now()->parse($paidAt) : now(),
            'paid_by_user_id' => $actorUserId,
            'payment_reference' => $paymentReference ?: $invoice->payment_reference,
            'payment_notes' => $paymentNotes ?: $invoice->payment_notes,
        ])->save();

        return $invoice->fresh(['subscription.gym', 'plan', 'paidBy']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function resolveCurrentWindow(GymPlatformSubscription $subscription): array
    {
        $periodStartsAt = $subscription->starts_at?->toDateString()
            ?? optional($subscription->created_at)->toDateString()
            ?? now()->toDateString();

        $periodEndsAt = $subscription->renews_at?->toDateString()
            ?? CarbonImmutable::parse($periodStartsAt)->addDays(30)->toDateString();

        return [$periodStartsAt, $periodEndsAt];
    }

    private function createInvoiceForWindow(
        GymPlatformSubscription $subscription,
        string $periodStartsAt,
        string $periodEndsAt,
        ?int $actorUserId,
        bool $includeSetupFee,
    ): GymPlatformSubscriptionInvoice {
        $existingInvoice = $subscription->invoices()
            ->whereDate('period_starts_at', $periodStartsAt)
            ->whereDate('period_ends_at', $periodEndsAt)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $subtotalAmount = round((float) $subscription->billing_amount, 2);
        $setupFeeAmount = $includeSetupFee ? round((float) $subscription->setup_fee_amount, 2) : 0.0;
        $discountAmount = 0.0;
        $taxAmount = 0.0;
        $totalAmount = round($subtotalAmount + $setupFeeAmount - $discountAmount + $taxAmount, 2);
        $dueAt = CarbonImmutable::parse($periodEndsAt);
        $isFree = $totalAmount <= 0;

        return DB::transaction(function () use (
            $subscription,
            $periodStartsAt,
            $periodEndsAt,
            $actorUserId,
            $subtotalAmount,
            $setupFeeAmount,
            $discountAmount,
            $taxAmount,
            $totalAmount,
            $dueAt,
            $isFree
        ): GymPlatformSubscriptionInvoice {
            $sequence = (int) $subscription->invoices()->count() + 1;
            $invoice = GymPlatformSubscriptionInvoice::query()->create([
                'gym_platform_subscription_id' => $subscription->id,
                'gym_id' => $subscription->gym_id,
                'platform_subscription_plan_id' => $subscription->platform_subscription_plan_id,
                'generated_by_user_id' => $actorUserId,
                'invoice_number' => sprintf('PLT-%s-%04d', str_pad((string) $subscription->id, 5, '0', STR_PAD_LEFT), $sequence),
                'status' => $isFree ? 'paid' : ($dueAt->isPast() ? 'overdue' : 'due'),
                'currency' => 'INR',
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodEndsAt,
                'issued_at' => now(),
                'due_at' => $dueAt->toDateString(),
                'paid_at' => $isFree ? now() : null,
                'subtotal_amount' => $subtotalAmount,
                'setup_fee_amount' => $setupFeeAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'metadata' => [
                    'subscription_status' => $subscription->status,
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'included_services' => $subscription->included_services ?? [],
                    'plan_snapshot' => $subscription->plan_snapshot,
                ],
            ]);

            if ($isFree) {
                $invoice->forceFill(['paid_by_user_id' => $actorUserId])->save();
            }

            return $invoice;
        });
    }
}
