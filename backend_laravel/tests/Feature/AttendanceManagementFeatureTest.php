<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Jobs\PublishRealtimeEvent;
use App\Models\BiometricDeviceCommand;
use App\Models\BiometricDeviceEvent;
use App\Models\BiometricMemberLink;
use App\Models\Branch;
use App\Models\Gym;
use App\Models\MemberMembership;
use App\Models\MemberProfile;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Biometric\BiometricEnrollmentService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

        $attendanceLogId = DB::table('attendance_logs')
            ->where('gym_id', $gym->id)
            ->where('member_id', $member->id)
            ->value('id');

        $presencePage = $this->actingAs($owner)
            ->get(route('web.gym.attendance.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Members currently in gym')
            ->assertSee('1 present');
        $this->assertCount(1, $presencePage->viewData('currentPresence'));

        $this->actingAs($owner)
            ->post(route('web.gym.attendance.checkout', [
                'gym' => $gym->id,
                'branch' => $branch->id,
                'attendanceLog' => $attendanceLogId,
            ]))
            ->assertRedirect();

        $this->assertNotNull(DB::table('attendance_logs')->where('id', $attendanceLogId)->value('checked_out_at'));

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

    public function test_owner_can_create_device_and_prepare_member_enrollment_without_typing_an_identifier(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);

        $this->actingAs($owner)
            ->post(route('web.gym.biometric-devices.store', ['gym' => $gym->id, 'branch' => $branch->id]), [
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'name' => 'Reception eSSL',
                'adapter_key' => 'essl_adms',
                'model' => 'AI-Face Jupiter',
                'serial_number' => 'ESSL-TEST-1',
                'modalities' => ['face', 'fingerprint'],
            ])
            ->assertRedirect(route('web.gym.biometric-devices.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertSessionHas('device_secret')
            ->assertSessionHas('device_uuid');

        $deviceId = DB::table('biometric_devices')->where('serial_number', 'ESSL-TEST-1')->value('id');

        $this->actingAs($owner)
            ->get(route('web.gym.biometric-devices.index', ['gym' => $gym->id, 'branch' => $branch->id]))
            ->assertOk()
            ->assertSee('Reception eSSL')
            ->assertSee('Setup instructions and gateway URLs');

        $this->actingAs($owner)
            ->put(route('web.gym.biometric-devices.update', ['gym' => $gym->id, 'branch' => $branch->id, 'device' => $deviceId]), [
                'name' => 'Reception eSSL Updated',
                'vendor' => 'eSSL',
                'model' => 'AI-Face Jupiter',
                'serial_number' => 'ESSL-TEST-1',
                'modalities' => ['face'],
                'configuration' => ['host' => '192.168.1.50', 'port' => 4370, 'password' => 'device-private-password'],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertDatabaseHas('biometric_devices', ['id' => $deviceId, 'name' => 'Reception eSSL Updated']);
        $storedConfiguration = (string) DB::table('biometric_devices')->where('id', $deviceId)->value('configuration');
        $this->assertStringNotContainsString('192.168.1.50', $storedConfiguration);
        $this->assertStringNotContainsString('device-private-password', $storedConfiguration);

        $this->actingAs($owner)
            ->post(route('web.gym.members.biometrics.store', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]), [
                'device_ids' => [$deviceId],
                'modalities' => ['face'],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $profile = MemberProfile::query()->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $link = BiometricMemberLink::query()->where('member_profile_id', $profile->id)->firstOrFail();
        $this->assertSame((string) (100000 + $profile->id), $link->external_user_id);
        $this->assertSame('pending_capture', $link->status);
        $this->assertSame($link->external_user_id, $profile->fresh()->biometric_identifier);

        $this->actingAs($owner)
            ->get(route('web.gym.members.biometrics.show', ['gym' => $gym->id, 'branch' => $branch->id, 'member' => $member->id]))
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($link->external_user_id);
    }

    public function test_authenticated_device_event_records_attendance_once_and_confirms_enrollment(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Connector terminal',
            'adapter_key' => 'essl_lan_connector',
            'modalities' => ['fingerprint'],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['fingerprint'], $owner)[0];
        $url = "/api/biometric/devices/{$setup['device']->uuid}/events";
        $payload = [
            'event_id' => 'device-event-9001',
            'external_user_id' => $link->external_user_id,
            'event_type' => 'check_in',
            'modality' => 'fingerprint',
            'occurred_at' => now()->toIso8601String(),
        ];
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];

        $this->postJson($url, $payload, $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.duplicate', false);

        $this->postJson($url, $payload, $headers)
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, BiometricDeviceEvent::query()->count());
        $this->assertSame(1, DB::table('attendance_logs')->where('biometric_device_id', $setup['device']->id)->count());
        $this->assertSame('enrolled', $link->fresh()->status);
        $this->assertTrue($profile->fresh()->biometric_enabled);
    }

    public function test_device_gateway_rejects_wrong_secret_and_raw_biometric_material(): void
    {
        [$owner, , $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Privacy test terminal',
            'adapter_key' => 'generic_manual',
            'modalities' => ['face'],
        ], $owner);
        $url = "/api/biometric/devices/{$setup['device']->uuid}/events";
        $payload = ['event_id' => 'private-1', 'external_user_id' => '100001', 'occurred_at' => now()->toIso8601String()];

        $this->postJson($url, $payload, ['X-GymAtlas-Device-Token' => 'wrong'])->assertUnauthorized();
        $this->postJson($url, $payload + ['face_image' => 'base64-data'], ['X-GymAtlas-Device-Token' => $setup['secret']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('face_image');
    }

    public function test_remote_capable_connector_receives_and_acknowledges_member_command(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Managed connector',
            'adapter_key' => 'connector_managed',
            'modalities' => ['face'],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['face'], $owner)[0];
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];

        $commandId = $this->getJson("/api/biometric/devices/{$setup['device']->uuid}/commands", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'upsert_user')
            ->assertJsonPath('data.0.payload.external_user_id', $link->external_user_id)
            ->json('data.0.id');

        $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/commands/{$commandId}/acknowledge", [
            'status' => 'completed',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('pending_capture', $link->fresh()->status);
        $this->assertNotNull($link->fresh()->last_synced_at);
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

    public function test_device_events_publish_permission_scoped_realtime_updates_once(): void
    {
        Queue::fake();
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Realtime terminal',
            'adapter_key' => 'essl_lan_connector',
            'modalities' => ['fingerprint'],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['fingerprint'], $owner)[0];
        Queue::fake();
        $payload = [
            'event_id' => 'realtime-event-1',
            'external_user_id' => $link->external_user_id,
            'event_type' => 'check_in',
            'modality' => 'fingerprint',
            'occurred_at' => now()->toIso8601String(),
        ];
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];

        $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/events", $payload, $headers)->assertAccepted();
        $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/events", $payload, $headers)
            ->assertAccepted()->assertJsonPath('data.duplicate', true);

        Queue::assertPushed(PublishRealtimeEvent::class, function (PublishRealtimeEvent $job) use ($gym, $branch): bool {
            return $job->path === 'internal/biometric'
                && $job->payload['gymId'] === $gym->id
                && $job->payload['branchId'] === $branch->id
                && $job->payload['event'] === 'biometric:event_processed'
                && $job->payload['data']['status'] === 'accepted';
        });
        Queue::assertPushed(PublishRealtimeEvent::class, 2);
    }

    public function test_connector_commands_are_leased_redelivered_and_acknowledged_idempotently(): void
    {
        Queue::fake();
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Leased connector',
            'adapter_key' => 'connector_managed',
            'modalities' => ['face'],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['face'], $owner)[0];
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];
        $url = "/api/biometric/devices/{$setup['device']->uuid}/commands";

        $first = $this->getJson($url, $headers)->assertOk()->assertJsonCount(1, 'data');
        $commandId = $first->json('data.0.id');
        $this->assertSame(1, $first->json('data.0.attempt'));
        $this->getJson($url, $headers)->assertOk()->assertJsonCount(0, 'data');

        $this->travel(31)->seconds();
        $this->getJson($url, $headers)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attempt', 2);

        $ackUrl = $url."/{$commandId}/acknowledge";
        $this->postJson($ackUrl, ['status' => 'completed'], $headers)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson($ackUrl, ['status' => 'failed', 'message' => 'late failure'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame('completed', BiometricDeviceCommand::query()->findOrFail($commandId)->status);
        $this->assertSame('pending_capture', $link->fresh()->status);
    }

    public function test_owner_can_recover_an_unmatched_device_event_without_creating_duplicate_attendance(): void
    {
        Queue::fake();
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Imported terminal',
            'adapter_key' => 'essl_lan_connector',
            'modalities' => ['fingerprint'],
        ], $owner);
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];

        $eventId = $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/events", [
            'event_id' => 'unmatched-recovery-1',
            'external_user_id' => 'legacy-employee-42',
            'event_type' => 'check_in',
            'modality' => 'fingerprint',
            'occurred_at' => now()->toIso8601String(),
        ], $headers)->assertAccepted()->assertJsonPath('data.status', 'unmatched')->json('data.event_id');

        $this->actingAs($owner)->post(route('web.gym.biometric-events.resolve', [
            'gym' => $gym->id,
            'branch' => $branch->id,
            'event' => $eventId,
        ]), ['member_id' => $member->id])->assertRedirect()->assertSessionHas('status');

        $event = BiometricDeviceEvent::query()->findOrFail($eventId);
        $this->assertSame('accepted', $event->status);
        $this->assertNotNull($event->attendance_log_id);
        $this->assertDatabaseHas('biometric_member_links', [
            'biometric_device_id' => $setup['device']->id,
            'member_profile_id' => MemberProfile::query()->where('gym_id', $gym->id)->where('user_id', $member->id)->value('id'),
            'external_user_id' => 'legacy-employee-42',
            'status' => 'enrolled',
        ]);

        $this->actingAs($owner)->post(route('web.gym.biometric-events.resolve', [
            'gym' => $gym->id,
            'branch' => $branch->id,
            'event' => $eventId,
        ]), ['member_id' => $member->id])->assertSessionHasErrors('event');
        $this->assertSame(1, DB::table('attendance_logs')->where('biometric_device_event_id', $eventId)->count());
    }

    public function test_heartbeat_reports_clock_drift_and_reconciler_marks_stale_devices_and_commands(): void
    {
        Queue::fake();
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Clock terminal',
            'adapter_key' => 'connector_managed',
            'modalities' => ['face'],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['face'], $owner)[0];
        $headers = ['X-GymAtlas-Device-Token' => $setup['secret']];

        $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/heartbeat", [
            'connector_version' => '2.4.1',
            'clock_at' => now()->subMinutes(8)->toIso8601String(),
        ], $headers)->assertOk()
            ->assertJsonPath('data.connector_version', '2.4.1')
            ->assertJsonPath('data.clock_warning', 'Device clock differs from the server by more than five minutes.');

        $device = $setup['device']->fresh();
        $this->assertSame('2.4.1', $device->connector_version);
        $this->assertGreaterThanOrEqual(479, abs((int) $device->clock_skew_seconds));
        $device->forceFill(['status' => 'online', 'last_seen_at' => now()->subMinutes(10)])->save();
        $command = BiometricDeviceCommand::query()->where('biometric_member_link_id', $link->id)->firstOrFail();
        $command->forceFill(['status' => 'dispatched', 'attempts' => 10, 'dispatched_at' => now()->subMinute()])->save();

        $this->artisan('biometric:reconcile-devices')->assertSuccessful();

        $this->assertSame('offline', $device->fresh()->status);
        $this->assertSame('failed', $command->fresh()->status);
        $this->assertSame('sync_error', $link->fresh()->status);
    }

    public function test_device_methods_in_use_by_member_mappings_cannot_be_removed(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Protected methods terminal',
            'adapter_key' => 'connector_managed',
            'modalities' => ['face', 'fingerprint'],
        ], $owner);
        app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['face'], $owner);

        $this->actingAs($owner)->put(route('web.gym.biometric-devices.update', [
            'gym' => $gym->id,
            'branch' => $branch->id,
            'device' => $setup['device']->id,
        ]), [
            'name' => $setup['device']->name,
            'vendor' => $setup['device']->vendor,
            'modalities' => ['fingerprint'],
        ])->assertSessionHasErrors('modalities');

        $this->assertContains('face', $setup['device']->fresh()->modalities);
    }

    public function test_ebioserver_webhook_translates_punches_and_deduplicates_retries(): void
    {
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'K90 Pro',
            'adapter_key' => 'essl_ebioserver',
            'serial_number' => 'K90-PRO-001',
            'modalities' => ['fingerprint', 'card'],
            'configuration' => [
                'server_url' => 'https://ebio.example.test/Webservice.asmx',
                'username' => 'atlas-api',
                'password' => 'private-password',
                'location_code' => 'MAIN',
            ],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['fingerprint'], $owner)[0];
        $url = "/api/integrations/essl/ebioserver/{$setup['device']->uuid}/{$setup['secret']}";
        $payload = [
            'EmployeeCode' => $link->external_user_id,
            'LogDate' => now()->format('Y-m-d H:i:s'),
            'DownloadDate' => now()->format('Y-m-d H:i:s'),
            'DeviceName' => 'Reception K90',
            'SerialNumber' => 'K90-PRO-001',
            'Direction' => 'In',
            'VerificationType' => 'Fingerprint',
        ];

        $this->postJson($url, $payload)->assertOk()->assertSeeText('Success');
        $this->postJson($url, $payload)->assertOk()->assertSeeText('Success');
        $this->postJson("/api/biometric/devices/{$setup['device']->uuid}/events", [
            'event_id' => 'must-not-bypass-serial-validation',
            'external_user_id' => $link->external_user_id,
            'occurred_at' => now()->toIso8601String(),
        ], ['X-GymAtlas-Device-Token' => $setup['secret']])->assertNotFound();

        $this->assertSame(1, BiometricDeviceEvent::query()->where('biometric_device_id', $setup['device']->id)->count());
        $this->assertSame(1, DB::table('attendance_logs')->where('biometric_device_id', $setup['device']->id)->count());
        $this->assertSame('enrolled', $link->fresh()->status);

        $this->postJson($url, array_replace($payload, ['SerialNumber' => 'OTHER-GYM-DEVICE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('SerialNumber');

        $password = '12345678901234567890123456789012';
        $configuration = $setup['device']->configuration;
        $configuration['webhook_encryption_enabled'] = true;
        $configuration['webhook_encryption_password'] = $password;
        $setup['device']->forceFill(['configuration' => $configuration])->save();
        $encryptedRecord = array_replace($payload, [
            'LogDate' => now()->addSecond()->format('Y-m-d H:i:s'),
            'Direction' => 'Out',
        ]);
        $ciphertext = openssl_encrypt(
            json_encode($encryptedRecord, JSON_THROW_ON_ERROR),
            'AES-256-CBC',
            $password,
            OPENSSL_RAW_DATA,
            str_repeat("\0", 16),
        );
        $this->postJson($url, ['data' => base64_encode($ciphertext)])->assertOk()->assertSeeText('Success');
        $this->assertSame(2, BiometricDeviceEvent::query()->where('biometric_device_id', $setup['device']->id)->count());
        $this->assertSame(1, DB::table('attendance_logs')->where('biometric_device_id', $setup['device']->id)->count());
    }

    public function test_ebioserver_dispatcher_provisions_member_without_exposing_credentials(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response('<definitions><service name="WebService" /></definitions>', 200, ['Content-Type' => 'text/xml']);
            }

            $method = str_contains($request->body(), 'GetDeviceLastPing')
                ? 'GetDeviceLastPing'
                : (str_contains($request->body(), 'DeviceCommand_EnrollFP') ? 'DeviceCommand_EnrollFP' : 'UpdateEmployee');
            $result = $method === 'GetDeviceLastPing' ? '2026-08-25 10:00:00' : 'Success';

            return Http::response(
                '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'.$method.'Response xmlns="http://tempuri.org/"><'.$method.'Result>'.$result.'</'.$method.'Result></'.$method.'Response></soap:Body></soap:Envelope>',
                200,
                ['Content-Type' => 'text/xml'],
            );
        });
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'K90 Pro',
            'adapter_key' => 'essl_ebioserver',
            'serial_number' => 'K90-PRO-002',
            'modalities' => ['fingerprint'],
            'configuration' => [
                'server_url' => 'https://ebio.example.test/Webservice.asmx',
                'username' => 'atlas-api',
                'password' => 'private-password',
                'location_code' => 'MAIN',
                'webhook_encryption_enabled' => true,
            ],
        ], $owner);
        $this->assertNotNull($setup['webhook_encryption_password']);
        $this->assertSame(32, strlen($setup['webhook_encryption_password']));
        $this->actingAs($owner)->post(route('web.gym.biometric-devices.test-connection', [
            'gym' => $gym->id,
            'branch' => $branch->id,
            'device' => $setup['device']->id,
        ]))->assertRedirect()->assertSessionHas('status');
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['fingerprint'], $owner)[0];

        $this->artisan('biometric:dispatch-ebioserver')->assertSuccessful();

        $upsert = BiometricDeviceCommand::query()->where('biometric_member_link_id', $link->id)->where('command_type', 'upsert_user')->firstOrFail();
        $capture = BiometricDeviceCommand::query()->where('biometric_member_link_id', $link->id)->where('command_type', 'enroll_fingerprint')->firstOrFail();
        $this->assertSame('completed', $upsert->status);
        $this->assertSame('queued', $capture->status);
        $this->assertSame('capture_queued', $link->fresh()->status);

        $this->artisan('biometric:dispatch-ebioserver')->assertSuccessful();
        $this->assertSame('completed', $capture->fresh()->status);
        $this->assertSame('capture_requested', $link->fresh()->status);
        Http::assertSent(function ($request) use ($link): bool {
            $body = $request->body();

            return $request->url() === 'https://ebio.example.test/Webservice.asmx'
                && $request->hasHeader('SOAPAction', '"http://tempuri.org/UpdateEmployee"')
                && str_contains($body, '<EmployeeCode>'.$link->external_user_id.'</EmployeeCode>')
                && str_contains($body, '<EmployeeLocation>MAIN</EmployeeLocation>')
                && str_contains($body, '<EmployeeVerificationType>Finger</EmployeeVerificationType>')
                && str_contains($body, 'private-password');
        });
        Http::assertSent(fn ($request): bool => $request->hasHeader('SOAPAction', '"http://tempuri.org/DeviceCommand_EnrollFP"')
            && str_contains($request->body(), '<DeviceSerialNumber>K90-PRO-002</DeviceSerialNumber>')
            && str_contains($request->body(), '<FPIndex>0</FPIndex>'));

        $this->assertStringNotContainsString('private-password', json_encode($setup['device']->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_ebioserver_inflight_provisioning_cannot_regress_a_revoked_mapping(): void
    {
        Queue::fake();
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $setup = app(BiometricEnrollmentService::class)->createDevice([
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'name' => 'Race-safe K90',
            'adapter_key' => 'essl_ebioserver',
            'serial_number' => 'K90-RACE-1',
            'modalities' => ['fingerprint'],
            'configuration' => [
                'server_url' => 'https://ebio-race.example.test/Webservice.asmx',
                'username' => 'atlas-api',
                'password' => 'private-password',
                'location_code' => 'MAIN',
            ],
        ], $owner);
        $link = app(BiometricEnrollmentService::class)->enroll($profile, [$setup['device']], ['fingerprint'], $owner)[0];
        $revokedDuringRequest = false;
        Http::fake(function ($request) use (&$revokedDuringRequest, $link) {
            if (! $revokedDuringRequest && str_contains($request->body(), '<UpdateEmployee')) {
                app(BiometricEnrollmentService::class)->revoke($link->fresh(['device', 'memberProfile']));
                $revokedDuringRequest = true;
            }

            return Http::response(
                '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><UpdateEmployeeResponse xmlns="http://tempuri.org/"><UpdateEmployeeResult>Success</UpdateEmployeeResult></UpdateEmployeeResponse></soap:Body></soap:Envelope>',
                200,
                ['Content-Type' => 'text/xml'],
            );
        });

        $this->artisan('biometric:dispatch-ebioserver')->assertSuccessful();

        $this->assertTrue($revokedDuringRequest);
        $this->assertSame('revoked', $link->fresh()->status);
        $this->assertDatabaseHas('biometric_device_commands', [
            'biometric_member_link_id' => $link->id,
            'command_type' => 'upsert_user',
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('biometric_device_commands', [
            'biometric_member_link_id' => $link->id,
            'command_type' => 'delete_user',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('biometric_device_commands', [
            'biometric_member_link_id' => $link->id,
            'command_type' => 'enroll_fingerprint',
        ]);
    }

    public function test_ebioserver_multi_location_revocation_preserves_other_terminal_access(): void
    {
        Queue::fake();
        Http::fake(function ($request) {
            $method = str_contains($request->body(), 'DeleteEmployee') ? 'DeleteEmployee' : 'UpdateEmployee';

            return Http::response(
                '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><'.$method.'Response xmlns="http://tempuri.org/"><'.$method.'Result>Success</'.$method.'Result></'.$method.'Response></soap:Body></soap:Envelope>',
                200,
                ['Content-Type' => 'text/xml'],
            );
        });
        [$owner, $member, $gym, $branch] = $this->makeGymScope(RoleName::GymOwner->value);
        $profile = MemberProfile::query()->with('user')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        $base = [
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'adapter_key' => 'essl_ebioserver',
            'modalities' => ['fingerprint'],
        ];
        $configuration = [
            'server_url' => 'https://multi-location.example.test/Webservice.asmx',
            'username' => 'shared-api-user',
            'password' => 'private-password',
        ];
        $first = app(BiometricEnrollmentService::class)->createDevice($base + [
            'name' => 'Front K90',
            'serial_number' => 'K90-FRONT',
            'configuration' => $configuration + ['location_code' => 'FRONT'],
        ], $owner);
        $second = app(BiometricEnrollmentService::class)->createDevice($base + [
            'name' => 'Back K90',
            'serial_number' => 'K90-BACK',
            'configuration' => $configuration + ['location_code' => 'BACK'],
        ], $owner);
        [$frontLink, $backLink] = app(BiometricEnrollmentService::class)->enroll(
            $profile,
            [$first['device'], $second['device']],
            ['fingerprint'],
            $owner,
        );

        $this->artisan('biometric:dispatch-ebioserver')->assertSuccessful();
        Http::assertSent(fn ($request): bool => str_contains($request->body(), '<EmployeeLocation>FRONT,BACK</EmployeeLocation>'));

        $anchorWebhook = "/api/integrations/essl/ebioserver/{$first['device']->uuid}/{$first['secret']}";
        $first['device']->forceFill(['is_active' => false, 'status' => 'disabled'])->save();
        $this->postJson($anchorWebhook, [
            'EmployeeCode' => $backLink->external_user_id,
            'LogDate' => now()->format('Y-m-d H:i:s'),
            'SerialNumber' => 'K90-BACK',
            'Direction' => 'IN',
            'VerificationType' => 'Finger',
        ])->assertOk()->assertSeeText('Success');
        $this->assertDatabaseHas('biometric_device_events', [
            'biometric_device_id' => $second['device']->id,
            'status' => 'accepted',
        ]);
        $first['device']->forceFill(['is_active' => true, 'status' => 'connected'])->save();

        app(BiometricEnrollmentService::class)->revoke($frontLink->fresh(['device', 'memberProfile.user']));
        $this->assertSame('revoked', $frontLink->fresh()->status);
        $this->assertSame('enrolled', $backLink->fresh()->status);
        $this->assertDatabaseHas('biometric_device_commands', [
            'biometric_member_link_id' => $frontLink->id,
            'command_type' => 'sync_user_locations',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('biometric_device_commands', [
            'biometric_member_link_id' => $frontLink->id,
            'command_type' => 'delete_user',
        ]);

        $this->postJson($anchorWebhook, [
            'EmployeeCode' => $frontLink->external_user_id,
            'LogDate' => now()->format('Y-m-d H:i:s'),
            'SerialNumber' => 'K90-FRONT',
            'Direction' => 'IN',
            'VerificationType' => 'Finger',
        ])->assertOk()->assertSeeText('Success');
        $this->assertDatabaseHas('biometric_device_events', [
            'biometric_device_id' => $first['device']->id,
            'status' => 'unmatched',
        ]);
        $this->assertSame(0, DB::table('attendance_logs')->where('biometric_device_id', $first['device']->id)->count());
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
