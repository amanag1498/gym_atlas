<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_gym_owner_can_record_manual_attendance_and_view_today_screen(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);

        $this->actingAs($owner)
            ->post(route('web.gym.attendance.manual.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'source_device' => 'feature-test',
            ])
            ->assertRedirect(route('web.gym.attendance.index', [
                'gym' => $gym->id,
                'branch' => $branch->id,
            ]));

        $this->assertDatabaseHas('attendance_logs', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'checked_in_by' => $owner->id,
            'check_in_method' => 'manual',
        ]);

        $this->actingAs($owner)
            ->get(route('web.gym.attendance.today', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee($member->name);
    }

    public function test_today_attendance_api_paginates_without_losing_the_total(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $gym->forceFill(['prevent_duplicate_same_day_checkins' => false])->save();
        $service = app(AttendanceService::class);
        $service->recordManualCheckIn($gym, $branch, $member, $owner, checkedInAt: now()->subMinute());
        $service->recordManualCheckIn($gym, $branch, $member, $owner, checkedInAt: now());
        $headers = [
            'X-Gym-Id' => (string) $gym->id,
            'X-Branch-Id' => (string) $branch->id,
        ];

        $firstId = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/attendance/today?per_page=1&page=1', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->json('data.items.0.id');

        $secondId = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/gym/attendance/today?per_page=1&page=2', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->json('data.items.0.id');

        $this->assertNotSame($firstId, $secondId);
    }

    public function test_biometric_scan_records_attendance_for_an_enabled_active_profile(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->update([
                'biometric_identifier' => 'scanner-member-42',
                'biometric_enabled' => true,
            ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/biometric-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'biometric_identifier' => '  scanner-member-42  ',
                'source_device' => 'front-desk-scanner',
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.check_in_method', 'biometric');

        $this->assertDatabaseHas('attendance_logs', [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'check_in_method' => 'biometric',
            'source_device' => 'front-desk-scanner',
        ]);
    }

    public function test_biometric_scan_rejects_an_inactive_gym_member_profile(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->update([
                'biometric_identifier' => 'inactive-member-scan',
                'biometric_enabled' => true,
                'is_active' => false,
                'status' => 'inactive',
                'membership_status' => 'inactive',
            ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/biometric-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'biometric_identifier' => 'inactive-member-scan',
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.member_id.0', 'Attendance is unavailable because this gym member profile is inactive.');

        $this->assertDatabaseMissing('attendance_logs', ['member_id' => $member->id]);
    }

    public function test_duplicate_day_is_calculated_in_the_branch_timezone(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $branch->forceFill(['timezone' => 'Pacific/Kiritimati'])->save();
        $localDay = Carbon::now('Pacific/Kiritimati')->startOfDay();
        $firstCheckIn = $localDay->copy()->addHour()->utc();
        $secondCheckIn = $localDay->copy()->addHours(20)->utc();
        $this->assertNotSame($firstCheckIn->toDateString(), $secondCheckIn->toDateString());

        MemberMembership::query()
            ->where('gym_id', $gym->id)
            ->where('member_id', $member->id)
            ->update([
                'start_date' => $localDay->copy()->subDay()->toDateString(),
                'expiry_date' => $localDay->copy()->addDay()->toDateString(),
            ]);

        $service = app(AttendanceService::class);
        $service->recordManualCheckIn($gym, $branch, $member, $owner, checkedInAt: $firstCheckIn);

        try {
            $service->recordManualCheckIn($gym, $branch, $member, $owner, checkedInAt: $secondCheckIn);
            $this->fail('A second check-in on the same branch-local day should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame('This member has already checked in today.', $exception->errors()['member_id'][0]);
        }
    }

    public function test_frozen_membership_is_not_reported_as_attendance_enabled(): void
    {
        [, $member, $gym] = $this->makeGymScope(RoleName::GymOwner->value);
        MemberMembership::query()
            ->where('gym_id', $gym->id)
            ->where('member_id', $member->id)
            ->update(['status' => 'frozen']);
        MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->update(['membership_status' => 'frozen']);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/status')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.message', 'Attendance is paused while your gym membership is frozen.');
    }

    public function test_member_biometric_profile_reports_readiness_without_exposing_the_identifier(): void
    {
        [, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->update([
                'biometric_identifier' => 'sensitive-scanner-8842',
                'biometric_enabled' => true,
            ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/biometric-profile')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.attendance_enabled', true)
            ->assertJsonPath('data.biometric_registered', true)
            ->assertJsonPath('data.biometric_identifier', null)
            ->assertJsonPath('data.biometric_identifier_masked', '••••••••••••••••••8842')
            ->assertJsonPath('data.branch_id', $branch->id);
    }

    public function test_biometric_identifier_is_scoped_to_a_gym(): void
    {
        [, $firstMember, $firstGym] = $this->makeGymScope(RoleName::GymOwner->value);
        [, $secondMember, $secondGym] = $this->makeGymScope(RoleName::GymOwner->value);

        MemberProfile::query()->where('gym_id', $firstGym->id)->where('user_id', $firstMember->id)->update([
            'biometric_identifier' => 'local-scanner-slot-7',
        ]);
        MemberProfile::query()->where('gym_id', $secondGym->id)->where('user_id', $secondMember->id)->update([
            'biometric_identifier' => 'local-scanner-slot-7',
        ]);

        $this->assertSame(2, MemberProfile::query()->where('biometric_identifier', 'local-scanner-slot-7')->count());
    }

    public function test_removed_member_qr_route_is_not_exposed_when_multiple_profiles_exist(): void
    {
        $this->seed(PermissionSeeder::class);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'name' => 'Scoped Attendance Gym',
            'slug' => 'scoped-attendance-gym',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Scoped Attendance Branch',
            'slug' => 'scoped-attendance-branch',
            'status' => 'active',
            'is_active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Scoped Attendance Plan',
            'duration_days' => 30,
            'plan_price' => 2500,
            'joining_fee' => 0,
            'status' => 'active',
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'membership_status' => 'inactive',
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);
        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
            'default_plan_price' => 2500,
            'default_joining_fee' => 0,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_fee_enabled' => false,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2500,
            'amount_paid' => 2500,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/qr-code')
            ->assertNotFound();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/member/attendance/qr-code')
            ->assertNotFound();
    }

    public function test_removed_admin_qr_scan_routes_are_not_exposed(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value, membershipStatus: 'expired');
        $plan = MembershipPlan::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Expired Plan',
            'duration_days' => 30,
            'plan_price' => 2000,
            'joining_fee' => 0,
            'pt_included' => false,
            'status' => 'active',
            'created_by_user_id' => $owner->id,
        ]);

        MemberMembership::query()->create([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => now()->subDays(60)->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(),
            'status' => 'expired',
            'default_plan_price' => 2000,
            'default_joining_fee' => 0,
            'custom_fee_enabled' => false,
            'custom_fee_amount' => 2000,
            'discount_type' => 'none',
            'discount_amount' => 0,
            'custom_joining_fee' => 0,
            'joining_fee_waived' => false,
            'partial_month_fee' => 0,
            'pt_custom_fee' => 0,
            'final_payable_amount' => 2000,
            'amount_paid' => 2000,
            'due_amount' => 0,
            'payment_status' => 'paid',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/qr-scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/gym/attendance/scan', [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
            ], [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertNotFound();
    }

    public function test_branch_manager_cannot_view_other_branch_member_attendance(): void
    {
        [$manager, $member, $gym, $branch] = $this->makeGymScope(RoleName::BranchManager->value);
        $otherBranch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Other Branch',
            'slug' => 'other-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);
        $otherMember = User::factory()->create();
        $otherMember->forceFill(['active_role' => RoleName::Member->value])->save();
        $otherMember->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $otherMember->id,
            'gym_id' => $gym->id,
            'branch_id' => $otherBranch->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->getJson(route('web.gym.attendance.search.members', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'branch_id' => $branch->id,
                'q' => $otherMember->email,
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($manager)
            ->getJson(route('web.gym.attendance.search.members', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'branch_id' => $branch->id,
                'q' => $member->email,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $member->id);

        $this->actingAs($manager)
            ->get(route('web.gym.members.attendance', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'member' => $otherMember->id,
            ]))
            ->assertNotFound();

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/gym/members/{$otherMember->id}/attendance?gym_id={$gym->id}&branch_id={$branch->id}", [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertNotFound();
    }

    public function test_gym_wide_profile_can_be_searched_and_viewed_through_its_membership_branch(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $member->id)
            ->update(['branch_id' => null]);
        app(AttendanceService::class)->recordManualCheckIn($gym, $branch, $member, $owner);

        $this->actingAs($owner)
            ->getJson(route('web.gym.attendance.search.members', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'branch_id' => $branch->id,
                'q' => $member->email,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $member->id);

        $this->actingAs($owner)
            ->get(route('web.gym.members.attendance', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'member' => $member->id,
            ]))
            ->assertOk()
            ->assertSee($member->name);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/gym/members/{$member->id}/attendance?gym_id={$gym->id}&branch_id={$branch->id}", [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.0.member_id', $member->id);
    }

    public function test_gym_staff_needs_manage_attendance_custom_permission(): void
    {
        [$staff, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymStaff->value, customPermissions: []);

        $this->actingAs($staff)
            ->get(route('web.gym.attendance.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertForbidden();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/gym/attendance', [
                'X-Gym-Id' => (string) $gym->id,
                'X-Branch-Id' => (string) $branch->id,
            ])
            ->assertForbidden();

        $gym->users()->syncWithoutDetaching([
            $staff->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
        ]);
        $branch->users()->syncWithoutDetaching([
            $staff->id => ['is_primary' => true, 'custom_permissions' => json_encode(['manage_attendance'])],
        ]);

        DB::enableQueryLog();

        $this->actingAs($staff)
            ->get(route('web.gym.attendance.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk();

        $this->assertAggregatesDoNotInheritDisplayOrder('checked_in_at');
        DB::disableQueryLog();
    }

    private function assertAggregatesDoNotInheritDisplayOrder(string $column): void
    {
        $invalidQuery = collect(DB::getQueryLog())->first(function (array $query) use ($column): bool {
            $sql = strtolower($query['query']);

            return preg_match('/select\s+(?:count|sum|avg)\s*\(/', $sql) === 1
                && str_contains($sql, 'order by')
                && str_contains($sql, $column);
        });

        $this->assertNull($invalidQuery, 'Aggregate query inherited display ordering: '.($invalidQuery['query'] ?? ''));
    }

    /**
     * @return array{0: User, 1: User, 2: Gym, 3: Branch}
     */
    private function makeGymScope(string $role, string $membershipStatus = 'active', array $customPermissions = ['manage_attendance']): array
    {
        $this->seed(PermissionSeeder::class);

        $actor = User::factory()->create([
            'is_active' => true,
            'active_role' => $role,
        ]);
        $actor->assignRole($role);

        $member = User::factory()->create([
            'is_active' => true,
            'active_role' => RoleName::Member->value,
        ]);
        $member->assignRole(RoleName::Member->value);

        $gym = Gym::query()->create([
            'owner_user_id' => $role === RoleName::GymOwner->value ? $actor->id : null,
            'name' => 'Attendance Gym',
            'slug' => 'attendance-gym-'.str()->random(6),
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
            'prevent_duplicate_same_day_checkins' => true,
        ]);

        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Attendance Branch',
            'slug' => 'attendance-branch-'.str()->random(6),
            'status' => 'active',
            'is_active' => true,
        ]);

        $gym->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode($customPermissions)],
            $member->id => ['is_primary' => false],
        ]);
        $branch->users()->syncWithoutDetaching([
            $actor->id => ['is_primary' => true, 'custom_permissions' => json_encode($customPermissions)],
            $member->id => ['is_primary' => false],
        ]);

        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'membership_status' => $membershipStatus,
            'is_active' => true,
        ]);

        if ($membershipStatus === 'active') {
            $plan = MembershipPlan::query()->create([
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'name' => 'Active Attendance Plan',
                'duration_days' => 30,
                'plan_price' => 1000,
                'joining_fee' => 0,
                'status' => 'active',
            ]);
            MemberMembership::query()->create([
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'member_id' => $member->id,
                'membership_plan_id' => $plan->id,
                'start_date' => now()->toDateString(),
                'expiry_date' => now()->addDays(30)->toDateString(),
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
                'amount_paid' => 1000,
                'due_amount' => 0,
                'payment_status' => 'paid',
            ]);
        }

        return [$actor, $member, $gym, $branch];
    }
}
