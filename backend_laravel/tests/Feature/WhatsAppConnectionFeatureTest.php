<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Gym;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppConsent;
use App\Models\WhatsAppOnboardingSession;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppConnectionService;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppConnectionFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta_whatsapp', [
            'graph_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
            'app_id' => 'atlas-meta-app',
            'app_secret' => 'meta-app-secret',
            'embedded_signup_config_id' => 'signup-config',
            'webhook_verify_token' => 'verify-me',
        ]);
    }

    public function test_embedded_signup_connects_a_tenant_and_encrypts_the_access_token(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/oauth/access_token*' => Http::response([
                'access_token' => 'secret-system-user-token',
                'expires_in' => 3600,
            ]),
            'https://graph.facebook.com/v23.0/10001/phone_numbers*' => Http::response([
                'data' => [[
                    'id' => '20001',
                    'display_phone_number' => '+91 90000 00000',
                    'verified_name' => 'Atlas Demo Gym',
                    'quality_rating' => 'GREEN',
                    'code_verification_status' => 'VERIFIED',
                ]],
            ]),
            'https://graph.facebook.com/v23.0/10001/subscribed_apps*' => Http::response(['success' => true]),
            'https://graph.facebook.com/v23.0/10001/message_templates*' => Http::response([
                'data' => [[
                    'id' => 'template-1',
                    'name' => 'membership_expiry',
                    'language' => 'en_US',
                    'category' => 'UTILITY',
                    'status' => 'APPROVED',
                    'components' => [],
                ]],
            ]),
            'https://graph.facebook.com/v23.0/10001*' => Http::response([
                'id' => '10001',
                'name' => 'Atlas Demo WABA',
            ]),
        ]);
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Atlas Demo Gym',
            'slug' => 'atlas-demo-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);

        $account = app(WhatsAppConnectionService::class)->connect(
            $gym,
            $owner,
            'one-time-embedded-code',
            '10001',
            null,
        );

        $this->assertSame('secret-system-user-token', $account->access_token);
        $this->assertNotSame(
            'secret-system-user-token',
            DB::table('whatsapp_business_accounts')->where('id', $account->id)->value('access_token'),
        );
        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'whatsapp_business_account_id' => $account->id,
            'phone_number_id' => '20001',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('whatsapp_templates', [
            'whatsapp_business_account_id' => $account->id,
            'name' => 'membership_expiry',
            'language' => 'en_US',
            'status' => 'approved',
        ]);
        $this->assertArrayNotHasKey('access_token', $account->toArray());
    }

    public function test_account_lookup_is_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $gymA = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Gym A',
            'slug' => 'gym-a',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);
        $gymB = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Gym B',
            'slug' => 'gym-b',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);
        WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gymA->id,
            'waba_id' => 'waba-a',
            'access_token' => 'encrypted-a',
        ]);

        $service = app(WhatsAppConnectionService::class);

        $this->assertSame('waba-a', $service->accountFor($gymA)?->waba_id);
        $this->assertNull($service->accountFor($gymB));
        $this->assertNull($service->accountFor(null));
    }

    public function test_webhook_verification_signature_and_replay_are_enforced(): void
    {
        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')
            ->assertOk()
            ->assertContent('12345');

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => '10001', 'changes' => []]],
        ], JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'meta-app-secret');

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)->assertOk();
        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $payload)->assertOk();

        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'payload_sha256' => hash('sha256', $payload),
            'status' => 'processed',
        ]);

        $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $payload)->assertUnauthorized();
    }

    public function test_embedded_signup_handoff_is_expiring_and_single_use(): void
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Onboarding Gym',
            'slug' => 'onboarding-gym',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        $handoff = app(WhatsAppOnboardingService::class)->start($gym, $owner);

        $this->get($handoff['url'])
            ->assertOk()
            ->assertSee('Connect WhatsApp Business')
            ->assertSee('never paste an API key')
            ->assertSee('Keep using WhatsApp Business app')
            ->assertSee('whatsapp_business_app_onboarding');
        $this->assertDatabaseHas('whatsapp_onboarding_sessions', [
            'gym_id' => $gym->id,
            'created_by_user_id' => $owner->id,
            'status' => 'pending',
        ]);

        $token = basename(parse_url($handoff['url'], PHP_URL_PATH));
        WhatsAppOnboardingSession::query()->firstOrFail()->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get(route('whatsapp.onboarding.show', ['token' => $token]))->assertNotFound();
    }

    public function test_stop_keyword_revokes_only_the_receiving_sender_scope(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['phone' => '+919876543210']);
        $gymA = Gym::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Sender A', 'slug' => 'sender-a', 'status' => 'active',
        ]);
        $gymB = Gym::query()->create([
            'owner_user_id' => $owner->id, 'name' => 'Sender B', 'slug' => 'sender-b', 'status' => 'active',
        ]);
        $account = WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gymA->id, 'waba_id' => 'scope-a', 'status' => 'connected',
        ]);
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'phone_number_id' => 'scope-a-phone',
            'is_primary' => true,
        ]);
        foreach ([$gymA, $gymB] as $gym) {
            WhatsAppConsent::query()->create([
                'user_id' => $member->id,
                'gym_id' => $gym->id,
                'purpose' => 'utility',
                'status' => 'granted',
                'phone_e164' => '+919876543210',
                'source' => 'member_app',
                'wording_version' => 'v1',
            ]);
        }
        $event = WhatsAppWebhookEvent::query()->create([
            'payload_sha256' => str_repeat('a', 64),
            'status' => 'pending',
            'payload' => [
                'entry' => [['changes' => [['value' => [
                    'metadata' => ['phone_number_id' => 'scope-a-phone'],
                    'messages' => [[
                        'id' => 'wamid.stop', 'from' => '919876543210',
                        'type' => 'text', 'text' => ['body' => 'STOP'],
                    ]],
                ]]]]],
            ],
        ]);

        (new ProcessWhatsAppWebhook($event->id))->handle();

        $this->assertDatabaseHas('whatsapp_consents', [
            'user_id' => $member->id, 'gym_id' => $gymA->id,
            'purpose' => 'utility', 'status' => 'revoked', 'source' => 'inbound_keyword',
        ]);
        $this->assertDatabaseHas('whatsapp_consents', [
            'user_id' => $member->id, 'gym_id' => $gymB->id,
            'purpose' => 'utility', 'status' => 'granted',
        ]);
    }

    public function test_gym_can_create_and_edit_a_template_without_accessing_raw_credentials(): void
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Template Gym',
            'slug' => 'template-gym',
            'status' => 'active',
        ]);
        $account = WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'template-waba',
            'access_token' => 'private-template-token',
            'status' => 'connected',
            'health_status' => 'healthy',
        ]);
        Http::fake([
            'https://graph.facebook.com/v23.0/template-waba/message_templates' => Http::response([
                'id' => 'provider-template-1', 'status' => 'PENDING', 'category' => 'UTILITY',
            ]),
            'https://graph.facebook.com/v23.0/provider-template-1' => Http::response([
                'success' => true, 'status' => 'PENDING',
            ]),
        ]);
        $service = app(WhatsAppConnectionService::class);
        $template = $service->createTemplate($account, [
            'name' => 'membership_expiry_custom',
            'language' => 'en_US',
            'category' => 'utility',
            'body' => 'Hi {{1}}, your membership expires on {{2}}.',
            'sample_values' => ['Aman', '31 August'],
        ]);
        $service->updateTemplate($account, $template, [
            'category' => 'utility',
            'body' => 'Hi {{1}}, renew before {{2}} to keep gym access.',
            'sample_values' => ['Aman', '31 August'],
        ]);

        $this->assertSame('pending', $template->fresh()->status);
        $this->assertSame('provider-template-1', $template->provider_template_id);
        $this->assertStringContainsString('renew before', $template->fresh()->components[0]['text']);
        $this->assertArrayNotHasKey('access_token', $account->toArray());
    }

    public function test_gym_and_platform_admin_receive_the_complete_automation_trigger_catalog(): void
    {
        $this->seed(PermissionSeeder::class);
        $owner = User::factory()->create([
            'active_role' => RoleName::GymOwner->value,
            'is_active' => true,
        ]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Routing Gym',
            'slug' => 'routing-gym',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Gym-Id', (string) $gym->id)
            ->getJson('/api/gym/communications/notification-types')
            ->assertOk()
            ->assertJsonCount(count(NotificationType::cases()), 'data')
            ->assertJsonFragment(['value' => NotificationType::WorkoutCompleted->value]);

        $platform = User::factory()->create([
            'active_role' => RoleName::PlatformAdmin->value,
            'is_active' => true,
        ]);
        $platform->assignRole(RoleName::PlatformAdmin->value);
        $this->actingAs($platform, 'sanctum')
            ->getJson('/api/platform-admin/communications/notification-types')
            ->assertOk()
            ->assertJsonCount(count(NotificationType::cases()), 'data')
            ->assertJsonFragment(['value' => NotificationType::MembershipPaused->value]);
    }
}
