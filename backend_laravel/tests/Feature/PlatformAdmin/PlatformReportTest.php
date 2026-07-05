<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\CustomFeeAuditLog;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\TrialRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_reports_pages_api_filters_and_exports_work(): void
    {
        $admin = User::factory()->create([
            'email' => 'reports-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $member = User::factory()->create(['is_active' => true, 'active_role' => RoleName::Member->value]);
        $member->assignRole(RoleName::Member->value);
        $trainer = User::factory()->create(['is_active' => true, 'active_role' => RoleName::Trainer->value]);
        $trainer->assignRole(RoleName::Trainer->value);
        $branchManager = User::factory()->create(['is_active' => false, 'active_role' => RoleName::BranchManager->value]);
        $branchManager->assignRole(RoleName::BranchManager->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Report Gym',
            'slug' => 'report-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);

        $pendingGym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Pending Gym',
            'slug' => 'pending-gym',
            'city' => 'Pune',
            'status' => 'pending',
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'report-main-branch',
            'city' => 'Mumbai',
            'status' => 'active',
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 3000,
            'joining_fee' => 500,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $admin->id,
        ]);

        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'expiry_date' => now()->addDays(25)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 3000,
            'default_joining_fee' => 500,
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 2500,
            'discount_type' => 'fixed',
            'discount_amount' => 500,
            'custom_joining_fee' => 250,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2750,
            'amount_paid' => 1000,
            'due_amount' => 1750,
            'due_date' => now()->subDay()->toDateString(),
            'payment_status' => 'partial',
            'custom_fee_reason' => 'Retention pricing',
            'approved_by_admin_id' => $admin->id,
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(2),
        ]);

        Payment::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_membership_id' => $membership->id,
            'member_id' => $member->id,
            'amount' => 1000,
            'payment_mode' => 'cash',
            'status' => 'recorded',
            'payment_status' => 'paid',
            'paid_at' => now()->subDay(),
            'payment_date' => now()->subDay(),
        ]);

        AttendanceLog::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'checked_in_by' => $trainer->id,
            'check_in_method' => 'manual',
            'checked_in_at' => now(),
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Trial Lead',
            'phone' => '9999999999',
            'email' => 'trial@example.com',
            'preferred_date' => now()->toDateString(),
            'status' => 'converted',
        ]);

        CustomFeeAuditLog::query()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'member_membership_id' => $membership->id,
            'old_values' => ['final_payable_amount' => 3500],
            'new_values' => ['final_payable_amount' => 2750],
            'changed_by' => $admin->id,
            'reason' => 'Retention pricing',
            'changed_at' => now()->subDay(),
        ]);

        $this->post('/admin/login', [
            'email' => 'reports-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.reports.index'))
            ->assertOk()
            ->assertSee('Platform Reports');

        $this->get(route('web.admin.reports.gyms', ['city' => 'Mumbai']))
            ->assertOk()
            ->assertSee('Gym Growth')
            ->assertSee('Mumbai');

        $this->get(route('web.admin.reports.users', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('User Growth');

        $this->get(route('web.admin.reports.payments', ['city' => 'Mumbai']))
            ->assertOk()
            ->assertSee('Payments Summary')
            ->assertSee('1,000.00');

        $this->get(route('web.admin.reports.attendance'))
            ->assertOk()
            ->assertSee('Attendance Summary');

        $this->get(route('web.admin.reports.custom-fees'))
            ->assertOk()
            ->assertSee('Custom Fee Usage')
            ->assertSee('Retention pricing');

        $paymentsExport = $this->get(route('web.admin.reports.export', ['type' => 'payments', 'city' => 'Mumbai']));
        $paymentsExport
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $gymsExport = $this->get(route('web.admin.reports.export', ['type' => 'gyms', 'city' => 'Mumbai']));
        $gymsExport
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Mumbai', $gymsExport->streamedContent());
        $this->assertStringNotContainsString('Pune', $gymsExport->streamedContent());

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_key', 'overview');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports/gyms?city=Mumbai')
            ->assertOk()
            ->assertJsonPath('data.report_key', 'gyms')
            ->assertJsonPath('data.summary_cards.0.value', '1')
            ->assertJsonPath('data.rows.0.0', 'Mumbai');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports/users?status=inactive')
            ->assertOk()
            ->assertJsonPath('data.report_key', 'users');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports/payments?city=Mumbai')
            ->assertOk()
            ->assertJsonPath('data.report_key', 'payments');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports/attendance')
            ->assertOk()
            ->assertJsonPath('data.report_key', 'attendance');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/reports/custom-fees')
            ->assertOk()
            ->assertJsonPath('data.report_key', 'custom-fees');
    }
}
