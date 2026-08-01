<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Billing\MemberMembershipLifecycleService;
use App\Services\Billing\MembershipEnrollmentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FormerGymMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_cancellation_clears_trainer_and_blocks_gym_and_trainer_direct_access_while_preserving_history(): void
    {
        [$owner, $trainer, $member, $gym, $branch, $plan, $membership] = $this->fixture('former');

        app(MemberMembershipLifecycleService::class)->cancel($membership);

        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertFalse($profile->is_active);
        $this->assertSame('inactive', $profile->status);
        $this->assertNull($profile->assigned_trainer_user_id);
        $this->assertDatabaseHas('member_memberships', ['id' => $membership->id, 'status' => 'cancelled']);

        $headers = ['X-Gym-Id' => (string) $gym->id, 'X-Branch-Id' => (string) $branch->id];
        $this->actingAs($owner, 'sanctum')->getJson('/api/gym/members/'.$member->id, $headers)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->putJson('/api/gym/members/'.$member->id, ['name' => 'Blocked'], $headers)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->getJson('/api/gym/members/'.$member->id.'/payments?gym_id='.$gym->id, $headers)->assertNotFound();
        $this->actingAs($owner, 'sanctum')->getJson('/api/gym/members/'.$member->id.'/attendance', $headers)->assertNotFound();

        $webScope = ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id];
        $this->actingAs($owner)->get(route('web.gym.members.show', $webScope))->assertNotFound();
        $this->actingAs($owner)->get(route('web.gym.members.edit', $webScope))->assertNotFound();
        $this->actingAs($owner)->get(route('web.gym.members.payments', $webScope))->assertNotFound();
        $this->actingAs($owner)->get(route('web.gym.members.attendance', $webScope))->assertNotFound();

        $this->actingAs($trainer, 'sanctum')->getJson('/api/trainer/assigned-members/'.$member->id)->assertUnprocessable();
        $this->actingAs($trainer, 'sanctum')->getJson('/api/trainer/assigned-members')->assertOk()->assertJsonCount(0, 'data');

        // A stale profile flag must never restore attendance after membership cancellation.
        $profile->update(['is_active' => true, 'status' => 'active', 'membership_status' => 'active']);
        try {
            app(AttendanceService::class)->recordManualCheckIn($gym, $branch, $member, $owner);
            $this->fail('Cancelled membership unexpectedly allowed an attendance check-in.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Attendance is unavailable because the member does not have an active membership.',
                $exception->errors()['member_id'][0],
            );
        }
    }

    public function test_same_gym_re_enrollment_creates_new_membership_and_restores_profile_without_overwriting_history(): void
    {
        [$owner, , $member, $gym, $branch, $plan, $oldMembership] = $this->fixture('reenroll');
        $lifecycle = app(MemberMembershipLifecycleService::class);
        $lifecycle->cancel($oldMembership);

        ['membership' => $newMembership] = app(MembershipEnrollmentService::class)->enroll($plan, $owner, [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ]);
        $lifecycle->syncMemberProfileFromMembership($newMembership->fresh(['member.memberProfile']));

        $this->assertNotSame($oldMembership->id, $newMembership->id);
        $this->assertDatabaseCount('member_memberships', 2);
        $this->assertDatabaseHas('member_memberships', ['id' => $oldMembership->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('member_memberships', ['id' => $newMembership->id, 'status' => 'active']);
        $profile = MemberProfile::query()->where('user_id', $member->id)->where('gym_id', $gym->id)->firstOrFail();
        $this->assertTrue($profile->is_active);
        $this->assertSame('active', $profile->membership_status);
        $this->assertNull($profile->assigned_trainer_user_id);
    }

    public function test_active_membership_at_another_gym_cannot_unlock_cancelled_profile(): void
    {
        [$ownerA, , $member, $gymA, $branchA, , $membershipA] = $this->fixture('gym-a');
        app(MemberMembershipLifecycleService::class)->cancel($membershipA);

        [, , , $gymB] = $this->fixture('gym-b', $member);
        $this->assertDatabaseHas('member_memberships', ['member_id' => $member->id, 'gym_id' => $gymB->id, 'status' => 'active']);

        $this->actingAs($ownerA, 'sanctum')
            ->getJson('/api/gym/members/'.$member->id, ['X-Gym-Id' => (string) $gymA->id, 'X-Branch-Id' => (string) $branchA->id])
            ->assertNotFound();
    }

    /** @return array{User, User, User, Gym, Branch, MembershipPlan, MemberMembership} */
    private function fixture(string $slug, ?User $member = null): array
    {
        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value, 'is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);
        $trainer = User::factory()->create(['active_role' => RoleName::Trainer->value, 'is_active' => true]);
        $trainer->assignRole(RoleName::Trainer->value);
        $member ??= User::factory()->create(['active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);
        $gym = Gym::query()->create(['owner_user_id' => $owner->id, 'name' => $slug, 'slug' => $slug, 'status' => 'active', 'approval_status' => 'approved', 'is_active' => true]);
        $branch = Branch::query()->create(['gym_id' => $gym->id, 'name' => $slug, 'slug' => $slug, 'status' => 'active', 'is_active' => true]);
        $owner->gyms()->attach($gym->id, ['role_name' => RoleName::GymOwner->value, 'status' => 'active', 'is_primary' => true]);
        $owner->branches()->attach($branch->id, ['is_primary' => true]);
        $trainer->gyms()->attach($gym->id, ['role_name' => RoleName::Trainer->value, 'status' => 'active']);
        $trainer->branches()->attach($branch->id);
        TrainerProfile::query()->create(['user_id' => $trainer->id, 'gym_id' => $gym->id, 'branch_id' => $branch->id, 'status' => 'active', 'is_active' => true]);
        $profile = MemberProfile::query()->updateOrCreate(['user_id' => $member->id, 'gym_id' => $gym->id], ['branch_id' => $branch->id, 'assigned_trainer_user_id' => $trainer->id, 'assigned_trainer_id' => $trainer->id, 'status' => 'active', 'membership_status' => 'active', 'membership_expires_on' => now()->addDays(30), 'is_active' => true]);
        $plan = MembershipPlan::query()->create(['gym_id' => $gym->id, 'branch_id' => $branch->id, 'name' => $slug, 'duration_days' => 30, 'plan_price' => 1000, 'joining_fee' => 0, 'status' => 'active']);
        $membership = MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now(),
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
            'default_plan_price' => 1000,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 1000,
            'amount_paid' => 0,
            'due_amount' => 1000,
            'payment_status' => 'unpaid',
        ]);

        return [$owner, $trainer, $member, $gym, $branch, $plan, $membership];
    }
}
