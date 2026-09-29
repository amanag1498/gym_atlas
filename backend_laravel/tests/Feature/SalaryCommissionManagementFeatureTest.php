<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CommissionEarning;
use App\Models\CompensationProfile;
use App\Models\Gym;
use App\Models\GymLedgerEntry;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\PayrollStatement;
use App\Models\User;
use App\Services\Billing\CommissionService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PayrollService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalaryCommissionManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_commission_uses_only_collected_extra_and_supports_percentage_and_fixed_rules(): void
    {
        [$owner, $trainer, $staff, $membership] = $this->fixtures();
        $commissions = app(CommissionService::class);
        $commissions->configure($membership, [
            ['recipient_user_id' => $trainer->id, 'recipient_type' => 'trainer', 'category' => 'pt', 'calculation_type' => 'percentage', 'value' => 50, 'recurrence' => 'recurring'],
            ['recipient_user_id' => $staff->id, 'recipient_type' => 'staff', 'category' => 'sales', 'calculation_type' => 'fixed', 'value' => 350, 'recurrence' => 'one_time'],
        ]);

        $payments = app(PaymentService::class);
        $first = $payments->recordPayment($membership->fresh(), $owner, ['amount' => 2000, 'payment_mode' => 'cash', 'paid_at' => now()]);
        $this->assertEquals(250.0, (float) CommissionEarning::query()->where('payment_id', $first->id)->where('recipient_user_id', $trainer->id)->value('amount'));
        $this->assertEquals(50.0, (float) CommissionEarning::query()->where('payment_id', $first->id)->where('recipient_user_id', $staff->id)->value('amount'));

        $second = $payments->recordPayment($membership->fresh(), $owner, ['amount' => 3000, 'payment_mode' => 'upi', 'paid_at' => now()]);
        $this->assertEquals(1750.0, (float) CommissionEarning::query()->where('recipient_user_id', $trainer->id)->where('status', 'earned')->sum('amount'));
        $this->assertEquals(350.0, (float) CommissionEarning::query()->where('recipient_user_id', $staff->id)->where('status', 'earned')->sum('amount'));

        $payments->reversePayment($first, 'Test correction');
        $this->assertEquals(750.0, (float) CommissionEarning::query()->where('recipient_user_id', $trainer->id)->where('status', 'earned')->sum('amount'));
        $this->assertEquals(150.0, (float) CommissionEarning::query()->where('recipient_user_id', $staff->id)->where('status', 'earned')->sum('amount'));
        $this->assertEquals(3000.0, (float) $second->fresh()->amount);
    }

    public function test_renewal_copies_only_recurring_commission_and_preserves_extra(): void
    {
        [$owner, $trainer, $staff, $membership] = $this->fixtures();
        app(CommissionService::class)->configure($membership, [
            ['recipient_user_id' => $trainer->id, 'recipient_type' => 'trainer', 'category' => 'pt', 'calculation_type' => 'percentage', 'value' => 60, 'recurrence' => 'recurring'],
            ['recipient_user_id' => $staff->id, 'recipient_type' => 'staff', 'category' => 'sales', 'calculation_type' => 'percentage', 'value' => 10, 'recurrence' => 'one_time'],
        ]);

        $result = app(MemberMembershipLifecycleService::class)->renew($membership, $owner, [
            'start_date' => $membership->expiry_date->copy()->addDay()->toDateString(),
            'due_date' => $membership->expiry_date->copy()->addMonth()->toDateString(),
            'amount_paid' => 0,
        ]);
        $renewed = $result['membership']->fresh('commissionAllocations');

        $this->assertEquals(3500.0, (float) $renewed->pt_custom_fee);
        $this->assertEquals(5000.0, (float) $renewed->final_payable_amount);
        $this->assertCount(1, $renewed->commissionAllocations);
        $this->assertSame($trainer->id, $renewed->commissionAllocations->first()->recipient_user_id);
        $this->assertSame('recurring', $renewed->commissionAllocations->first()->recurrence);
    }

    public function test_monthly_statement_combines_salary_and_commission_and_posts_payout_to_ledger(): void
    {
        [$owner, $trainer, , $membership, $gym, $branch] = $this->fixtures();
        app(CommissionService::class)->configure($membership, [
            ['recipient_user_id' => $trainer->id, 'recipient_type' => 'trainer', 'category' => 'pt', 'calculation_type' => 'percentage', 'value' => 50, 'recurrence' => 'recurring'],
        ]);
        app(PaymentService::class)->recordPayment($membership, $owner, ['amount' => 5000, 'payment_mode' => 'cash', 'paid_at' => now()]);
        CompensationProfile::query()->create(['gym_id' => $gym->id, 'branch_id' => $branch->id, 'user_id' => $trainer->id, 'worker_type' => 'trainer', 'monthly_salary' => 10000, 'payout_day' => 5, 'is_active' => true]);

        $payroll = app(PayrollService::class);
        $payroll->generate($gym, now()->startOfMonth());
        $statement = PayrollStatement::query()->where('user_id', $trainer->id)->firstOrFail();
        $this->assertEquals(10000.0, (float) $statement->salary_amount);
        $this->assertEquals(1750.0, (float) $statement->commission_amount);
        $this->assertEquals(11750.0, (float) $statement->net_payable_amount);

        $payout = $payroll->pay($statement, $owner, ['amount' => 11750, 'payment_mode' => 'bank', 'reference' => 'BANK-1', 'paid_at' => now()]);
        $this->assertSame('paid', $statement->fresh()->status);
        $entry = GymLedgerEntry::query()->findOrFail($payout->gym_ledger_entry_id);
        $this->assertSame('payroll', $entry->category);
        $this->assertSame('outflow', $entry->direction);
        $this->assertEquals(11750.0, (float) $entry->amount);
    }

    public function test_combined_commission_cannot_exceed_commissionable_extra(): void
    {
        [, $trainer, $staff, $membership] = $this->fixtures();
        $this->expectException(ValidationException::class);
        app(CommissionService::class)->configure($membership, [
            ['recipient_user_id' => $trainer->id, 'calculation_type' => 'percentage', 'value' => 90],
            ['recipient_user_id' => $staff->id, 'calculation_type' => 'fixed', 'value' => 1000],
        ]);
    }

    public function test_gym_owner_can_open_salary_and_commission_workspace(): void
    {
        [$owner, , , , $gym] = $this->fixtures();

        $this->actingAs($owner)
            ->get(route('web.gym.compensation.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Salary &amp; Commission', false)
            ->assertSee('Generate monthly statements');
    }

    private function fixtures(): array
    {
        $owner = $this->roleUser(RoleName::GymOwner->value);
        $trainer = $this->roleUser(RoleName::Trainer->value);
        $staff = $this->roleUser(RoleName::GymStaff->value);
        $member = $this->roleUser(RoleName::Member->value);
        $gym = Gym::query()->create(['owner_user_id' => $owner->id, 'name' => 'Commission Gym', 'slug' => fake()->unique()->slug(), 'status' => 'active', 'approval_status' => 'approved', 'is_active' => true]);
        $branch = Branch::query()->create(['gym_id' => $gym->id, 'name' => 'Main', 'slug' => fake()->unique()->slug(), 'status' => 'active', 'is_active' => true]);
        foreach ([$owner, $trainer, $staff] as $person) {
            $person->gyms()->attach($gym->id, ['is_primary' => true]);
            $person->branches()->attach($branch->id, ['is_primary' => true]);
        }
        MemberProfile::query()->create(['user_id' => $member->id, 'gym_id' => $gym->id, 'branch_id' => $branch->id, 'assigned_trainer_user_id' => $trainer->id, 'membership_status' => 'active', 'is_active' => true]);
        $plan = MembershipPlan::query()->create(['gym_id' => $gym->id, 'branch_id' => $branch->id, 'name' => 'Monthly PT', 'duration_days' => 30, 'plan_price' => 1500, 'joining_fee' => 0, 'pt_included' => true, 'status' => 'active', 'created_by_user_id' => $owner->id]);
        $membership = MemberMembership::query()->create(['gym_id' => $gym->id, 'branch_id' => $branch->id, 'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'start_date' => now()->toDateString(), 'expiry_date' => now()->addDays(30)->toDateString(), 'status' => 'active', 'default_plan_price' => 1500, 'default_joining_fee' => 0, 'custom_fee_enabled' => false, 'custom_fee_amount' => 0, 'discount_type' => 'none', 'discount_amount' => 0, 'custom_joining_fee' => 0, 'joining_fee_waived' => true, 'partial_month_fee' => 0, 'pt_custom_fee' => 3500, 'final_payable_amount' => 5000, 'amount_paid' => 0, 'due_amount' => 5000, 'due_date' => now()->addDays(30), 'payment_status' => 'unpaid', 'custom_fee_reason' => 'PT package', 'approved_by_admin_id' => $owner->id]);

        return [$owner, $trainer, $staff, $membership, $gym, $branch, $member];
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create(['active_role' => $role, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
