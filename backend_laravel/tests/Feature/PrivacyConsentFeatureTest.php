<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Privacy\ConsentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrivacyConsentFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedPrivacyConsentsForFixtureUsers = false;

    public function test_consent_state_records_grant_and_withdrawal_history(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);
        $request = Request::create('/api/public/privacy/consents', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'consent-test',
        ]);

        $service->record($user, 'core_account', $request);
        $service->record($user, 'health_and_fitness_data', $request);
        $this->assertContains('core_account', $service->state($user)['required_purposes']);
        $health = collect($service->state($user)['items'])
            ->firstWhere('purpose', 'health_and_fitness_data');
        $this->assertTrue($health['granted']);

        $service->withdraw($user, 'health_and_fitness_data', $request);
        $state = $service->state($user);
        $health = collect($state['items'])->firstWhere('purpose', 'health_and_fitness_data');

        $this->assertFalse($health['granted']);
        $this->assertFalse($service->granted($user, 'health_and_fitness_data'));
        $this->assertStringContainsString('Terms of Service', $user->consentRecords()->where('purpose', 'core_account')->firstOrFail()->notice_snapshot);
        $this->assertCount(3, $user->consentRecords()->get());
    }

    public function test_withdrawal_blocks_protected_routes_and_deletes_push_tokens(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);
        $request = Request::create('/api/public/privacy/consents', 'POST');
        $service->record($user, 'core_account', $request);
        $service->record($user, 'health_and_fitness_data', $request);
        $service->record($user, 'notifications', $request);

        $this->actingAs($user)->postJson('/api/public/fcm-tokens', [
            'token' => 'test-device-token',
        ])->assertSuccessful();
        $this->assertDatabaseHas('user_fcm_tokens', ['user_id' => $user->id]);

        $service->withdraw($user, 'notifications', $request);
        $service->withdraw($user, 'health_and_fitness_data', $request);
        $this->assertDatabaseMissing('user_fcm_tokens', ['user_id' => $user->id]);
        $this->actingAs($user)->postJson('/api/public/fcm-tokens', [
            'token' => 'test-device-token-2',
        ])->assertForbidden();
        $this->assertFalse($service->granted($user, 'health_and_fitness_data'));
    }

    public function test_known_minor_cannot_self_grant_consent(): void
    {
        $user = User::factory()->create(['date_of_birth' => now()->subYears(16)->toDateString()]);
        $request = Request::create('/api/public/privacy/consents', 'POST');

        $this->expectException(HttpException::class);
        app(ConsentService::class)->record($user, 'core_account', $request);
    }

    public function test_privacy_requests_are_private_and_pending_requests_are_not_duplicated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/public/privacy/requests', [
            'type' => 'access',
        ])->assertStatus(202);
        $this->actingAs($user, 'sanctum')->postJson('/api/public/privacy/requests', [
            'type' => 'access',
        ])->assertStatus(202);
        $this->assertDatabaseCount('privacy_requests', 1);
        $this->actingAs($other, 'sanctum')->getJson('/api/public/privacy/requests')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_platform_admin_can_review_and_close_a_privacy_request(): void
    {
        $this->seed(PermissionSeeder::class);
        $member = User::factory()->create();
        $admin = User::factory()->create(['active_role' => RoleName::PlatformAdmin->value]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        $requestId = $this->actingAs($member, 'sanctum')->postJson('/api/public/privacy/requests', [
            'type' => 'correction',
            'details' => 'Please correct my account name.',
        ])->assertStatus(202)->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/privacy-requests?status=pending')
            ->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/platform-admin/privacy-requests/'.$requestId, [
                'status' => 'fulfilled',
                'resolution_note' => 'Verified and corrected in the source record.',
            ])->assertOk();
        $this->assertDatabaseHas('privacy_requests', [
            'id' => $requestId,
            'status' => 'fulfilled',
            'processed_by_user_id' => $admin->id,
        ]);
    }

    public function test_core_withdrawal_blocks_feature_access_but_keeps_privacy_requests_available(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/public/privacy/consents/core_account', 'DELETE');
        $consents = app(ConsentService::class);
        $consents->record($user, 'core_account', $request);
        $consents->record($user, 'notifications', $request);
        $consents->withdraw($user, 'core_account', $request);

        $this->assertFalse($consents->granted($user, 'core_account'));
        $this->actingAs($user, 'sanctum')->getJson('/api/public/notification-preferences')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/public/privacy/requests')->assertOk();
    }
}
