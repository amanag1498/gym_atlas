<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyMembershipPaymentBackfillFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_membership_without_payment_row_is_backfilled_once(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Legacy Payment Gym',
            'slug' => 'legacy-payment-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Legacy Payment Branch',
            'slug' => 'legacy-payment-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Legacy Plan',
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 1000,
            'default_joining_fee' => 0,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 1000,
            'amount_paid' => 600,
            'due_amount' => 400,
            'due_date' => now()->addDays(30)->toDateString(),
            'payment_status' => 'partial',
            'approved_by_admin_id' => $owner->id,
        ]);

        $migration = require database_path('migrations/2026_07_29_160000_backfill_missing_membership_payment_records.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'member_membership_id' => $membership->id,
            'member_id' => $member->id,
            'amount' => 600,
            'status' => 'recorded',
        ]);
        $this->assertDatabaseCount('payment_receipts', 1);
        $this->assertDatabaseCount('gym_ledger_entries', 1);
    }
}
