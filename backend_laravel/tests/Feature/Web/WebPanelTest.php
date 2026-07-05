<?php

namespace Tests\Feature\Web;

use App\Enums\RoleName;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\TrainerProfile;
use App\Models\TrialRequest;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use App\Services\Authorization\ScopedPermissionResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WebPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_login_to_web_panel(): void
    {
        $admin = User::factory()->create([
            'email' => 'platform-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'platform-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Platform Dashboard');
    }

    public function test_platform_admin_cannot_use_gym_web_panel_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-only@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/gym/login', [
            'email' => 'admin-only@example.com',
            'password' => 'secret123',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_member_cannot_use_any_web_admin_panel_login(): void
    {
        $member = User::factory()->create([
            'email' => 'member-only@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);

        $this->post('/admin/login', [
            'email' => 'member-only@example.com',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->post('/gym/login', [
            'email' => 'member-only@example.com',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_gym_owner_can_access_only_own_gym_member_page(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $ownGym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Own Gym',
            'slug' => 'own-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $ownBranch = Branch::query()->create([
            'gym_id' => $ownGym->id,
            'name' => 'Own Branch',
            'slug' => 'own-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $otherOwner = User::factory()->create(['is_active' => true]);
        $otherOwner->assignRole(RoleName::GymOwner->value);
        $otherGym = Gym::query()->create([
            'owner_user_id' => $otherOwner->id,
            'name' => 'Other Gym',
            'slug' => 'other-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $otherBranch = Branch::query()->create([
            'gym_id' => $otherGym->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $ownMember = $this->createMemberFor($ownGym, $ownBranch, 'own-member@example.com');
        $otherMember = $this->createMemberFor($otherGym, $otherBranch, 'other-member@example.com');

        $this->post('/gym/login', [
            'email' => $owner->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->get(route('web.gym.members.show', $ownMember))->assertOk();
        $this->get(route('web.gym.members.show', $otherMember))->assertNotFound();
    }

    public function test_branch_manager_can_only_access_assigned_branch_members(): void
    {
        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $manager->id,
            'name' => 'Manager Gym',
            'slug' => 'manager-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);

        $memberA = $this->createMemberFor($gym, $branchA, 'branch-a-member@example.com');
        $memberB = $this->createMemberFor($gym, $branchB, 'branch-b-member@example.com');

        $this->post('/gym/login', [
            'email' => $manager->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $this->get(route('web.gym.members.show', $memberA))->assertOk();
        $this->get(route('web.gym.members.show', $memberB))->assertNotFound();
    }

    public function test_branch_manager_web_lists_only_show_accessible_branches_and_trainers(): void
    {
        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $manager->id,
            'name' => 'Scoped UI Gym',
            'slug' => 'scoped-ui-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Visible Branch',
            'slug' => 'visible-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Hidden Branch',
            'slug' => 'hidden-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainerA = User::factory()->create(['name' => 'Visible Trainer', 'is_active' => true]);
        $trainerA->assignRole(RoleName::Trainer->value);
        $trainerB = User::factory()->create(['name' => 'Hidden Trainer', 'is_active' => true]);
        $trainerB->assignRole(RoleName::Trainer->value);

        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);
        $this->attachToGymAndBranches($trainerA, $gym, [$branchA], []);
        $this->attachToGymAndBranches($trainerB, $gym, [$branchB], []);

        $trainerA->managedTrainerProfile()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'is_active' => true,
        ]);
        $trainerB->managedTrainerProfile()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branchB->id,
            'is_active' => true,
        ]);

        $this->loginGymUser($manager);

        $this->get(route('web.gym.members.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Visible Branch')
            ->assertDontSee('Hidden Branch')
            ->assertSee('Visible Trainer')
            ->assertDontSee('Hidden Trainer');

        $this->get(route('web.gym.staff.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Visible Branch')
            ->assertDontSee('Hidden Branch');

        $this->get(route('web.gym.branches.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Visible Branch')
            ->assertDontSee('Hidden Branch');
    }

    public function test_branch_manager_member_export_respects_branch_scope(): void
    {
        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $manager->id,
            'name' => 'Export Scope Gym',
            'slug' => 'export-scope-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'export-branch-a',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'export-branch-b',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);
        $visibleMember = $this->createMemberFor($gym, $branchA, 'visible-export@example.com');
        $hiddenMember = $this->createMemberFor($gym, $branchB, 'hidden-export@example.com');

        $this->loginGymUser($manager);

        $response = $this->get(route('web.gym.members.index', [
            'gym' => $gym->id,
            'export' => 'csv',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('visible-export@example.com', $csv);
        $this->assertStringNotContainsString('hidden-export@example.com', $csv);
    }

    public function test_staff_needs_custom_fee_permission_for_web_custom_fee_updates(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Billing Gym',
            'slug' => 'billing-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Billing Branch',
            'slug' => 'billing-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $member = $this->createMemberFor($gym, $branch, 'billing-member@example.com');
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 1000,
            'joining_fee' => 100,
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
            'default_joining_fee' => 100,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 1100,
            'amount_paid' => 0,
            'due_amount' => 1100,
            'due_date' => now()->toDateString(),
            'payment_status' => 'unpaid',
        ]);

        $staff = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $this->attachToGymAndBranches($staff, $gym, [$branch], []);

        $this->post('/gym/login', [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));

        $payload = [
            'custom_fee_enabled' => 1,
            'custom_fee_amount' => 900,
            'discount_type' => 'fixed',
            'discount_amount' => 50,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => 1,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'due_date' => now()->toDateString(),
            'custom_fee_reason' => 'Staff test',
        ];

        $this->post(route('web.gym.memberships.custom-fee.update', $membership), $payload)->assertForbidden();

        $this->attachToGymAndBranches($staff, $gym, [$branch], ['edit_custom_fee']);

        $freshStaff = $staff->fresh(['roles', 'gyms', 'branches']);
        $this->assertTrue(app(ScopeResolver::class)->canAccessGym($freshStaff, $gym));
        $this->assertTrue(app(ScopeResolver::class)->canAccessBranch($freshStaff, $branch));
        $this->assertTrue(app(ScopedPermissionResolver::class)->hasPermission($freshStaff, 'edit_custom_fee', $gym->id, $branch->id));

        $this->post(route('web.gym.memberships.custom-fee.update', $membership), $payload)
            ->assertRedirect();

        $this->assertTrue((bool) $membership->fresh()->custom_fee_enabled);
    }

    public function test_gym_staff_cannot_open_sensitive_management_pages_without_permission(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Restricted Gym',
            'slug' => 'restricted-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Restricted Branch',
            'slug' => 'restricted-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $this->attachToGymAndBranches($staff, $gym, [$branch], []);

        $this->loginGymUser($staff);

        $this->get(route('web.gym.profile.edit', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();
        $this->get(route('web.gym.public-listing.edit', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();
        $this->get(route('web.gym.staff.index', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();
        $this->get(route('web.gym.payments.create', ['gym' => $gym->id, 'branch' => $branch->id]))->assertForbidden();
    }

    public function test_gym_owner_can_assign_membership_create_payment_record_attendance_and_manage_trials(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Operations Gym',
            'slug' => 'operations-gym',
            'approval_status' => 'approved',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
            'trial_available' => true,
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Operations Branch',
            'slug' => 'operations-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $member = $this->createMemberFor($gym, $branch, 'ops-member@example.com');
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Quarterly',
            'duration_days' => 90,
            'plan_price' => 3000,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);
        $trialRequest = TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Lead User',
            'phone' => '9999999999',
            'email' => 'lead@example.com',
            'preferred_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->loginGymUser($owner);

        $this->get(route('web.gym.dashboard', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Gym Dashboard');

        $this->post(route('web.gym.members.assign-membership.store', ['member' => $member, 'gym' => $gym->id, 'branch' => $branch->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_paid' => 500,
        ])->assertRedirect(route('web.gym.members.custom-fee', ['member' => $member]));

        $membership = MemberMembership::query()->where('member_id', $member->id)->latest('id')->firstOrFail();
        $this->assertSame(3200.0, (float) $membership->final_payable_amount);
        $this->assertSame(500.0, (float) $membership->amount_paid);
        $this->assertSame(2700.0, (float) $membership->due_amount);

        $this->post(route('web.gym.payments.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'member_membership_id' => $membership->id,
            'amount' => 700,
            'payment_mode' => 'cash',
        ])->assertRedirect(route('web.gym.payments.index'));

        $membership->refresh();
        $this->assertSame(1200.0, (float) $membership->amount_paid);
        $this->assertSame(2000.0, (float) $membership->due_amount);
        $this->assertDatabaseCount('payments', 2);

        $this->post(route('web.gym.attendance.manual.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'source_device' => 'web-test',
        ])->assertRedirect(route('web.gym.attendance.index'));

        $this->assertDatabaseCount('attendance_logs', 1);

        $this->post(route('web.gym.announcements.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'audience_type' => 'branch_specific',
            'title' => 'Branch Update',
            'message' => 'Bring your towel.',
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'title' => 'Branch Update',
        ]);

        $this->put(route('web.gym.trial-requests.update', ['trialRequest' => $trialRequest, 'gym' => $gym->id, 'branch' => $branch->id]), [
            'status' => 'accepted',
            'notes' => 'Confirmed for tomorrow',
        ])->assertRedirect();

        $this->assertSame('accepted', $trialRequest->fresh()->status);

        $this->get(route('web.gym.reports.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Gym Reports Overview');
    }

    public function test_gym_owner_can_preview_and_import_members_from_csv(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Import Gym',
            'slug' => 'import-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Import Branch',
            'slug' => 'import-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['name' => 'Assigned Trainer', 'is_active' => true]);
        $trainer->assignRole(RoleName::Trainer->value);
        $this->attachToGymAndBranches($trainer, $gym, [$branch], []);
        $trainer->managedTrainerProfile()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly Elite',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 100,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $this->loginGymUser($owner);

        $csv = implode("\n", [
            'name,email,phone,goal,branch,membership_plan,start_date,trainer',
            'Import Member,import-member@example.com,9999999999,Fat loss,Import Branch,Monthly Elite,2026-05-07,Assigned Trainer',
        ]);

        $previewResponse = $this->post(route('web.gym.members.import.preview', [
            'gym' => $gym->id,
            'branch' => $branch->id,
        ]), [
            'members_csv' => UploadedFile::fake()->createWithContent('members.csv', $csv),
        ]);

        $previewResponse
            ->assertOk()
            ->assertSee('Import Preview')
            ->assertSee('Ready');

        $previewKey = collect(session()->all())->keys()->first(fn (string $key): bool => str_starts_with($key, 'gym_member_import_preview:'));

        $this->assertNotNull($previewKey);

        $previewToken = str_replace('gym_member_import_preview:', '', $previewKey);

        $this->post(route('web.gym.members.import.store', [
            'gym' => $gym->id,
            'branch' => $branch->id,
        ]), [
            'preview_token' => $previewToken,
        ])->assertRedirect(route('web.gym.members.index', [
            'gym' => $gym->id,
            'branch' => $branch->id,
        ]));

        $member = User::query()->where('email', 'import-member@example.com')->first();

        $this->assertNotNull($member);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'fitness_goal' => 'Fat loss',
        ]);
        $this->assertDatabaseHas('member_memberships', [
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
        ]);
    }

    public function test_branch_manager_can_view_trial_requests_when_scoped_to_branch(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trials Gym',
            'slug' => 'trials-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Trials Branch',
            'slug' => 'trials-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Scoped Lead',
            'phone' => '1234567890',
            'email' => 'scoped@example.com',
            'preferred_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $this->attachToGymAndBranches($manager, $gym, [$branch], []);

        $this->loginGymUser($manager);

        $this->get(route('web.gym.trial-requests.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Scoped Lead');
    }

    public function test_gym_owner_dashboard_stats_are_accurate(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Dashboard Gym',
            'slug' => 'dashboard-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Dashboard Branch',
            'slug' => 'dashboard-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole(RoleName::Trainer->value);
        $this->attachToGymAndBranches($trainer, $gym, [$branch], []);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $memberA = $this->createMemberFor($gym, $branch, 'dash-a@example.com');
        $memberB = $this->createMemberFor($gym, $branch, 'dash-b@example.com');

        MemberProfile::query()->where('user_id', $memberA->id)->update([
            'assigned_trainer_user_id' => $trainer->id,
            'membership_status' => 'active',
            'membership_expires_on' => now()->addDays(3)->toDateString(),
        ]);
        MemberProfile::query()->where('user_id', $memberB->id)->update([
            'is_active' => false,
            'membership_status' => 'expired',
            'membership_expires_on' => now()->subDay()->toDateString(),
        ]);

        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Dashboard Plan',
            'duration_days' => 30,
            'plan_price' => 3000,
            'joining_fee' => 200,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $memberA->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(2)->toDateString(),
            'expiry_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 3000,
            'default_joining_fee' => 200,
            'custom_fee_enabled' => true,
            'custom_fee_amount' => 2500,
            'discount_type' => 'fixed',
            'discount_amount' => 500,
            'custom_joining_fee' => 200,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2700,
            'amount_paid' => 1000,
            'due_amount' => 1700,
            'due_date' => now()->subDay()->toDateString(),
            'payment_status' => 'overdue',
        ]);

        Payment::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $memberA->id,
            'member_membership_id' => MemberMembership::query()->where('member_id', $memberA->id)->value('id'),
            'amount' => 1000,
            'payment_mode' => 'cash',
            'status' => 'recorded',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_date' => now(),
        ]);

        AttendanceLog::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $memberA->id,
            'checked_in_by' => $trainer->id,
            'check_in_method' => 'manual',
            'checked_in_at' => now(),
        ]);

        TrialRequest::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Pending Trial',
            'status' => 'pending',
            'preferred_date' => now()->addDay()->toDateString(),
        ]);

        $this->loginGymUser($owner);

        $response = $this->get(route('web.gym.dashboard', ['gym' => $gym->id, 'branch' => $branch->id]));
        $response->assertOk()->assertSee('Gym Dashboard');

        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['total_members']);
        $this->assertSame(1, $stats['active_members']);
        $this->assertSame(1, $stats['expired_members']);
        $this->assertSame(1, $stats['expiring_soon']);
        $this->assertSame(1, $stats['today_check_ins']);
        $this->assertSame(1700.0, $stats['pending_dues']);
        $this->assertSame(1700.0, $stats['overdue_dues']);
        $this->assertSame(1000.0, $stats['monthly_collection']);
        $this->assertSame(1, $stats['custom_fee_members_count']);
        $this->assertSame(1, $stats['total_trainers']);
        $this->assertSame(2.0, $stats['trainer_member_ratio']);
        $this->assertSame(1, $stats['pending_trial_requests']);
        $this->assertSame(1, $stats['members_without_trainer_count']);
    }

    public function test_branch_manager_dashboard_is_branch_scoped(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Scoped Dashboard Gym',
            'slug' => 'scoped-dashboard-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branchA = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'slug' => 'branch-a',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branchB = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'slug' => 'branch-b',
            'status' => 'active',
            'is_active' => true,
        ]);

        $memberA = $this->createMemberFor($gym, $branchA, 'scope-a@example.com');
        $memberB = $this->createMemberFor($gym, $branchB, 'scope-b@example.com');

        $manager = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $manager->assignRole(RoleName::BranchManager->value);
        $this->attachToGymAndBranches($manager, $gym, [$branchA], []);

        $this->loginGymUser($manager);

        $response = $this->get(route('web.gym.dashboard', ['gym' => $gym->id, 'branch' => $branchA->id]));
        $response->assertOk();

        $stats = $response->viewData('stats');
        $membersWithoutTrainer = $response->viewData('membersWithoutTrainer');

        $this->assertSame(1, $stats['total_members']);
        $this->assertSame(1, $stats['members_without_trainer_count']);
        $this->assertCount(1, $membersWithoutTrainer);
        $this->assertSame($memberA->id, $membersWithoutTrainer->first()->user_id);
        $this->assertNotSame($memberB->id, $membersWithoutTrainer->first()->user_id);
    }

    public function test_gym_staff_dashboard_visibility_matches_permissions(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Staff Dashboard Gym',
            'slug' => 'staff-dashboard-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Staff Branch',
            'slug' => 'staff-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $staff = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $staff->assignRole(RoleName::GymStaff->value);
        $this->attachToGymAndBranches($staff, $gym, [$branch], ['manage_attendance']);

        $this->loginGymUser($staff);

        $response = $this->get(route('web.gym.dashboard', ['gym' => $gym->id, 'branch' => $branch->id]));
        $response->assertOk();

        $visibility = $response->viewData('visibility');
        $quickActions = collect($response->viewData('quickActions'))->filter(fn (array $action) => $action['visible'])->pluck('label')->values()->all();

        $this->assertTrue($visibility['attendance']);
        $this->assertTrue($visibility['billing']);
        $this->assertTrue($visibility['trainers']);
        $this->assertTrue($visibility['announcements']);
        $this->assertFalse($visibility['collect_payment_action']);
        $this->assertFalse($visibility['manage_trainers_action']);
        $this->assertFalse($visibility['send_announcements_action']);
        $this->assertContains('Mark Attendance', $quickActions);
        $this->assertNotContains('Collect Payment', $quickActions);
        $this->assertNotContains('Add Trainer', $quickActions);
        $this->assertNotContains('Send Announcement', $quickActions);
    }

    public function test_web_logout_invalidates_session_and_blocks_dashboard_access(): void
    {
        $owner = User::factory()->create([
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Logout Gym',
            'slug' => 'logout-gym',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Logout Branch',
            'slug' => 'logout-branch',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($owner, $gym, [$branch], []);

        $this->loginGymUser($owner);

        $this->post(route('web.logout'))
            ->assertRedirect(route('web.admin.login'));

        $this->get(route('web.gym.dashboard', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertRedirect('/gym/login');
    }

    private function createMemberFor(Gym $gym, Branch $branch, string $email): User
    {
        $member = User::factory()->create([
            'email' => $email,
            'is_active' => true,
        ]);
        $member->assignRole(RoleName::Member->value);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->attachToGymAndBranches($member, $gym, [$branch], []);

        return $member;
    }

    /**
     * @param  list<Branch>  $branches
     * @param  list<string>  $customPermissions
     */
    private function attachToGymAndBranches(User $user, Gym $gym, array $branches, array $customPermissions): void
    {
        $gymPayload = [
            'custom_permissions' => json_encode($customPermissions),
            'is_primary' => true,
        ];

        if ($user->gyms()->where('gyms.id', $gym->id)->exists()) {
            $user->gyms()->updateExistingPivot($gym->id, $gymPayload);
        } else {
            $user->gyms()->attach($gym->id, $gymPayload);
        }

        foreach ($branches as $branch) {
            $branchPayload = [
                'custom_permissions' => json_encode($customPermissions),
                'is_primary' => false,
            ];

            if ($user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->updateExistingPivot($branch->id, $branchPayload);
            } else {
                $user->branches()->attach($branch->id, $branchPayload);
            }
        }
    }

    private function loginGymUser(User $user): void
    {
        $this->post('/gym/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('web.gym.dashboard'));
    }
}
