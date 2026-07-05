<?php

namespace Tests\Unit\Billing;

use App\Enums\PaymentMode;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Billing\MembershipPricingService;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculation_uses_the_membership_snapshot_not_the_live_plan_price(): void
    {
        [$gym, $branch, $member] = $this->makeBillingContext();

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Locked Price Plan',
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 100,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $pricingService = app(MembershipPricingService::class);
        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'due_date' => now()->toDateString(),
            ...$pricingService->buildMembershipPayload($plan, []),
        ]);

        $plan->update([
            'plan_price' => 5000,
            'joining_fee' => 500,
        ]);

        $pricingService->recalculateMembership($membership);

        $this->assertSame(1000.0, (float) $membership->default_plan_price);
        $this->assertSame(100.0, (float) $membership->default_joining_fee);
        $this->assertSame(1100.0, (float) $membership->final_payable_amount);
        $this->assertSame(1100.0, (float) $membership->due_amount);
    }

    public function test_it_blocks_overpayment_unless_explicitly_allowed(): void
    {
        [$gym, $branch, $member, $actor] = $this->makeBillingContext(withActor: true);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly Plan',
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'due_date' => now()->toDateString(),
            ...app(MembershipPricingService::class)->buildMembershipPayload($plan, []),
        ]);

        $paymentService = app(PaymentService::class);

        $this->expectException(ValidationException::class);

        $paymentService->recordPayment($membership, $actor, [
            'amount' => 1200,
            'payment_mode' => PaymentMode::Cash->value,
        ]);
    }

    public function test_it_allows_overpayment_only_when_explicitly_requested(): void
    {
        [$gym, $branch, $member, $actor] = $this->makeBillingContext(withActor: true);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly Plan',
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'due_date' => now()->toDateString(),
            ...app(MembershipPricingService::class)->buildMembershipPayload($plan, []),
        ]);

        $payment = app(PaymentService::class)->recordPayment($membership, $actor, [
            'amount' => 1200,
            'payment_mode' => PaymentMode::Cash->value,
            'allow_overpayment' => true,
        ]);

        $this->assertNotNull($payment->receipt);

        $membership->refresh();
        $this->assertSame(1200.0, (float) $membership->amount_paid);
        $this->assertSame(-200.0, (float) $membership->due_amount);
        $this->assertSame('overpaid', $membership->payment_status);
    }

    /**
     * @return array{0: Gym, 1: Branch, 2: User, 3?: User}
     */
    private function makeBillingContext(bool $withActor = false): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Unit Billing Gym',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'approval_status' => 'approved',
            'public_listing_approval_status' => 'approved',
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Unit Billing Branch',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);

        if (! $withActor) {
            return [$gym, $branch, $member];
        }

        $actor = User::factory()->create();

        return [$gym, $branch, $member, $actor];
    }
}
