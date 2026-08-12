<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\DietPlan;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\ScheduledReminder;
use App\Models\User;
use App\Models\WorkoutPlan;
use App\Models\WorkoutSession;
use App\Services\Audit\AuditTimelineService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MembershipLifecycleReconciliationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow('2026-08-12 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_reconciliation_ends_terminal_gym_access_and_preserves_personal_history(): void
    {
        [$member, $trainer, $gym, $branch, $plan] = $this->makeMembershipContext();
        $membership = $this->makeMembership($member, $gym, $branch, $plan, '2026-07-01', '2026-08-11');

        $personalPlan = WorkoutPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => null,
            'created_by_user_id' => $member->id,
            'plan_origin' => 'catalog_adopted',
            'is_member_editable' => true,
            'name' => 'Member-owned plan',
            'duration_weeks' => 4,
            'status' => 'active',
        ]);
        $gymPlan = WorkoutPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id,
            'plan_origin' => 'trainer_assigned',
            'is_member_editable' => false,
            'name' => 'Gym assignment',
            'duration_weeks' => 4,
            'status' => 'active',
        ]);
        $personalSession = $this->makeSession($member, $personalPlan, $branch, null);
        $gymSession = $this->makeSession($member, $gymPlan, $branch, $trainer);
        $dietPlan = DietPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id,
            'name' => 'Gym diet assignment',
            'status' => 'active',
        ]);
        $membershipReminder = $this->makeReminder($member, $gym, $branch, $membership, 'membership_expiry');
        $attendanceReminder = $this->makeReminder($member, $gym, $branch, null, 'attendance_inactivity');

        $this->artisan('memberships:reconcile-lifecycle', ['--date' => '2026-08-12'])
            ->expectsOutputToContain('1 expired, 0 profile activations, 1 terminal access revocations')
            ->assertSuccessful();

        $this->assertSame('expired', $membership->fresh()->status);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'expired',
            'membership_expires_on' => '2026-08-11 00:00:00',
            'assigned_trainer_user_id' => null,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => null,
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('gym_user', ['gym_id' => $gym->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('branch_user', ['branch_id' => $branch->id, 'user_id' => $member->id]);

        $this->assertDatabaseHas('workout_plans', [
            'id' => $personalPlan->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('workout_sessions', [
            'id' => $personalSession->id,
            'gym_id' => null,
            'branch_id' => null,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('workout_plans', ['id' => $gymPlan->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('workout_sessions', ['id' => $gymSession->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('diet_plans', ['id' => $dietPlan->id, 'status' => 'inactive']);
        $this->assertSame('cancelled', $membershipReminder->fresh()->status);
        $this->assertSame('cancelled', $attendanceReminder->fresh()->status);

        $audit = ActivityLog::query()->where('event', 'membership.expired')->sole();
        $this->assertNull($audit->actor_user_id);
        $this->assertSame('daily_lifecycle_reconciliation', $audit->context['source']);
        $this->assertFalse($audit->context['has_current_or_future_cycle']);
        $timelineItem = app(AuditTimelineService::class)->forActivityLogs([$audit])[0];
        $this->assertSame('Membership expired', $timelineItem['title']);
        $this->assertSame('membership_expired', $timelineItem['icon']);
        $this->assertSame('warning', $timelineItem['tone']);

        $this->artisan('memberships:reconcile-lifecycle', ['--date' => '2026-08-12'])
            ->expectsOutputToContain('0 expired')
            ->assertSuccessful();
        $this->assertSame(1, ActivityLog::query()->where('event', 'membership.expired')->count());
    }

    public function test_adjacent_renewal_expires_old_cycle_without_interrupting_gym_access(): void
    {
        [$member, $trainer, $gym, $branch, $plan] = $this->makeMembershipContext();
        $expired = $this->makeMembership($member, $gym, $branch, $plan, '2026-07-12', '2026-08-11');
        $renewal = $this->makeMembership($member, $gym, $branch, $plan, '2026-08-12', '2026-09-11');
        $gymPlan = WorkoutPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id,
            'plan_origin' => 'trainer_assigned',
            'is_member_editable' => false,
            'name' => 'Continuing assignment',
            'duration_weeks' => 4,
            'status' => 'active',
        ]);
        $dietPlan = DietPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'created_by_user_id' => $trainer->id,
            'name' => 'Continuing diet',
            'status' => 'active',
        ]);
        $oldReminder = $this->makeReminder($member, $gym, $branch, $expired, 'membership_expiry');

        $this->artisan('memberships:reconcile-lifecycle', ['--date' => '2026-08-12'])
            ->expectsOutputToContain('1 expired, 0 profile activations, 0 terminal access revocations')
            ->assertSuccessful();

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('active', $renewal->fresh()->status);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'active',
            'membership_expires_on' => '2026-09-11 00:00:00',
            'assigned_trainer_user_id' => $trainer->id,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('gym_user', ['gym_id' => $gym->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $branch->id, 'user_id' => $member->id]);
        $this->assertSame('active', $gymPlan->fresh()->status);
        $this->assertSame('active', $dietPlan->fresh()->status);
        $this->assertSame('cancelled', $oldReminder->fresh()->status);
        $this->assertTrue(ActivityLog::query()
            ->where('event', 'membership.expired')
            ->firstOrFail()
            ->context['has_current_or_future_cycle']);
    }

    public function test_future_renewal_keeps_relationships_and_activates_on_its_start_date(): void
    {
        [$member, $trainer, $gym, $branch, $plan] = $this->makeMembershipContext();
        $expired = $this->makeMembership($member, $gym, $branch, $plan, '2026-07-12', '2026-08-11');
        $renewal = $this->makeMembership($member, $gym, $branch, $plan, '2026-08-15', '2026-09-14');

        $this->artisan('memberships:reconcile-lifecycle', ['--date' => '2026-08-12'])
            ->assertSuccessful();

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('active', $renewal->fresh()->status);
        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'inactive',
            'assigned_trainer_user_id' => $trainer->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('gym_user', ['gym_id' => $gym->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $branch->id, 'user_id' => $member->id]);

        $this->artisan('memberships:reconcile-lifecycle', ['--date' => '2026-08-15'])
            ->expectsOutputToContain('1 profile activations')
            ->assertSuccessful();

        $this->assertDatabaseHas('member_profiles', [
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'membership_status' => 'active',
            'membership_expires_on' => '2026-09-14 00:00:00',
            'assigned_trainer_user_id' => $trainer->id,
            'is_active' => true,
        ]);
    }

    private function makeMembershipContext(): array
    {
        $owner = $this->makeRoleUser(RoleName::GymOwner->value);
        $member = $this->makeRoleUser(RoleName::Member->value);
        $trainer = $this->makeRoleUser(RoleName::Trainer->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Lifecycle Automation Gym',
            'slug' => 'lifecycle-automation-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => 'lifecycle-main-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 0,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        $member->gyms()->attach($gym->id);
        $member->branches()->attach($branch->id);
        $trainer->gyms()->attach($gym->id);
        $trainer->branches()->attach($branch->id);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'assigned_trainer_id' => $trainer->id,
            'membership_status' => 'active',
            'membership_expires_on' => '2026-08-11',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$member, $trainer, $gym, $branch, $plan];
    }

    private function makeRoleUser(string $role): User
    {
        $user = User::factory()->create(['active_role' => $role]);
        $user->assignRole($role);

        return $user;
    }

    private function makeMembership(
        User $member,
        Gym $gym,
        Branch $branch,
        MembershipPlan $plan,
        string $startsOn,
        string $expiresOn,
    ): MemberMembership {
        return MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startsOn,
            'expiry_date' => $expiresOn,
            'status' => 'active',
            'default_plan_price' => 2000,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2000,
            'amount_paid' => 2000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);
    }

    private function makeSession(
        User $member,
        WorkoutPlan $workoutPlan,
        Branch $branch,
        ?User $trainer,
    ): WorkoutSession {
        return WorkoutSession::query()->create([
            'gym_id' => $branch->gym_id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer?->id,
            'workout_plan_id' => $workoutPlan->id,
            'started_by_user_id' => $member->id,
            'session_date' => '2026-08-11',
            'status' => 'active',
            'started_at' => '2026-08-11 09:00:00',
        ]);
    }

    private function makeReminder(
        User $member,
        Gym $gym,
        Branch $branch,
        ?MemberMembership $membership,
        string $type,
    ): ScheduledReminder {
        return ScheduledReminder::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_membership_id' => $membership?->id,
            'type' => $type,
            'title' => 'Lifecycle reminder',
            'body' => 'Lifecycle reminder body',
            'scheduled_for' => '2026-08-13 09:00:00',
            'status' => 'pending',
        ]);
    }
}
