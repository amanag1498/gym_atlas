<?php

namespace Tests\Feature\Web;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\CommunicationCampaign;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunicationPanelFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
        config()->set('services.meta_whatsapp', [
            'graph_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
            'app_id' => 'public-meta-app-id',
            'app_secret' => 'server-only-secret',
            'embedded_signup_config_id' => 'public-signup-config',
            'webhook_verify_token' => 'server-only-verify-token',
        ]);
    }

    public function test_gym_owner_can_use_complete_laravel_communications_workspace_without_secret_exposure(): void
    {
        [$owner, $gym, $branch] = $this->gymOwnerFixture('web-comms');
        $member = $this->memberFixture($gym, $branch);
        [$account, $template] = $this->connectedAccount($gym, $owner, 'gym-web-waba');

        $page = $this->actingAs($owner)->get(route('web.gym.communications.index', ['gym' => $gym->id]));
        $page->assertOk()
            ->assertSee('Member communications')
            ->assertSee('WhatsApp Business')
            ->assertSee('Create message template')
            ->assertSee('Create campaign')
            ->assertSee('Notification routing')
            ->assertSee('WhatsApp inbox')
            ->assertSee($template->name)
            ->assertSee($member->name)
            ->assertSee('data-template-select', false)
            ->assertDontSee('encrypted-access-token')
            ->assertDontSee('server-only-secret')
            ->assertDontSee('server-only-verify-token');

        $this->actingAs($owner)->post(route('web.gym.communications.campaigns.store', ['gym' => $gym->id]), [
            'name' => 'Renewal reminders',
            'audience_type' => 'gym',
            'in_app_enabled' => '1',
            'in_app_title' => 'Membership reminder',
            'in_app_body' => 'Your membership renewal is coming up.',
            'whatsapp_enabled' => '1',
            'whatsapp_template_id' => $template->id,
        ])->assertRedirect();

        $campaign = CommunicationCampaign::query()->where('gym_id', $gym->id)->firstOrFail();
        $this->assertCount(2, $campaign->channels);
        $this->assertDatabaseHas('communication_campaign_channels', ['communication_campaign_id' => $campaign->id, 'channel' => 'in_app']);
        $this->assertDatabaseHas('communication_campaign_channels', ['communication_campaign_id' => $campaign->id, 'channel' => 'whatsapp']);

        $this->actingAs($owner)->put(route('web.gym.communications.automations.store', ['gym' => $gym->id]), [
            'notification_type' => NotificationType::MembershipExpiry->value,
            'in_app_enabled' => '1',
            'whatsapp_enabled' => '1',
            'whatsapp_template_id' => $template->id,
            'is_enabled' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('communication_automation_rules', [
            'gym_id' => $gym->id,
            'notification_type' => NotificationType::MembershipExpiry->value,
            'in_app_enabled' => true,
            'whatsapp_enabled' => true,
            'is_enabled' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('web.gym.communications.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Open any rule to edit its active state')
            ->assertSee('data-confirm-submit', false)
            ->assertSee('Save changes');
    }

    public function test_laravel_panel_starts_a_scope_bound_embedded_signup_session(): void
    {
        [$owner, $gym] = $this->gymOwnerFixture('onboarding-web');

        $response = $this->actingAs($owner)->post(route('web.gym.communications.whatsapp.onboarding', ['gym' => $gym->id]));

        $response->assertRedirectContains('/whatsapp/onboarding/');
        $this->assertDatabaseHas('whatsapp_onboarding_sessions', [
            'gym_id' => $gym->id,
            'created_by_user_id' => $owner->id,
            'status' => 'pending',
        ]);
        $this->assertStringNotContainsString('server-only-secret', (string) $response->headers->get('Location'));
    }

    public function test_laravel_inbox_uses_the_selected_template_and_validates_runtime_values(): void
    {
        Http::fake([
            'https://graph.facebook.com/v23.0/*/messages' => Http::response(['messages' => [['id' => 'wamid.web-reply']]]),
        ]);
        [$owner, $gym] = $this->gymOwnerFixture('inbox-web');
        [$account] = $this->connectedAccount($gym, $owner, 'inbox-web-waba');
        $phone = $account->phoneNumbers()->firstOrFail();
        $template = WhatsAppTemplate::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'provider_template_id' => 'reply-template',
            'name' => 'member_reply',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}}']],
        ]);
        $conversation = WhatsAppConversation::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'whatsapp_phone_number_id' => $phone->id,
            'contact_wa_id' => '919000000000',
            'contact_name' => 'Inbox Member',
            'status' => 'open',
            'service_window_expires_at' => now()->addHour(),
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('web.gym.communications.inbox.reply', [
            'gym' => $gym->id,
            'conversation' => $conversation->id,
        ]), [
            'whatsapp_template_id' => $template->id,
            'template_parameters_text' => 'Only one value',
        ])->assertSessionHasErrors('configuration.template_parameter_values');
        $this->assertDatabaseCount('whatsapp_messages', 0);

        $this->actingAs($owner)->post(route('web.gym.communications.inbox.reply', [
            'gym' => $gym->id,
            'conversation' => $conversation->id,
        ]), [
            'body' => 'This text must not override the selected template.',
            'whatsapp_template_id' => $template->id,
            'template_parameters_text' => "{member_name}\nWelcome back",
        ])->assertRedirect();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_conversation_id' => $conversation->id,
            'provider_message_id' => 'wamid.web-reply',
            'message_type' => 'template',
            'status' => 'sent',
        ]);
        Http::assertSent(fn ($request): bool => data_get($request->data(), 'template.components.0.parameters.0.text') === 'Inbox Member'
            && data_get($request->data(), 'template.components.0.parameters.1.text') === 'Welcome back');
    }

    public function test_platform_admin_has_an_isolated_native_laravel_workspace(): void
    {
        $admin = User::factory()->create(['active_role' => RoleName::PlatformAdmin->value, 'is_active' => true]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        $this->connectedAccount(null, $admin, 'platform-web-waba');

        $this->actingAs($admin)
            ->get(route('web.admin.communications.index'))
            ->assertOk()
            ->assertSee('Platform communications')
            ->assertSee('Connect WhatsApp Business')
            ->assertSee('Create campaign')
            ->assertSee('Communications', false)
            ->assertDontSee('encrypted-access-token')
            ->assertDontSee('server-only-secret');

        $this->actingAs($admin)->post(route('web.admin.communications.campaigns.store'), [
            'name' => 'Platform notice',
            'audience_type' => 'all_members',
            'in_app_enabled' => '1',
            'in_app_title' => 'Atlas update',
            'in_app_body' => 'A platform update is available.',
        ])->assertRedirect();

        $this->assertDatabaseHas('communication_campaigns', [
            'gym_id' => null,
            'name' => 'Platform notice',
            'audience_type' => 'all_members',
        ]);
    }

    public function test_gym_owner_cannot_manage_another_gyms_campaign_from_web_routes(): void
    {
        [$owner, $gym] = $this->gymOwnerFixture('owner-scope');
        [$otherOwner, $otherGym] = $this->gymOwnerFixture('other-scope');
        $campaign = CommunicationCampaign::query()->create([
            'gym_id' => $otherGym->id,
            'name' => 'Other gym campaign',
            'audience_type' => 'gym',
            'audience_filters' => [],
            'status' => 'draft',
            'created_by_user_id' => $otherOwner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('web.gym.communications.campaigns.send', ['gym' => $gym->id, 'campaign' => $campaign->id]))
            ->assertNotFound();
    }

    private function gymOwnerFixture(string $slug): array
    {
        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value, 'is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => str($slug)->headline().' Gym',
            'slug' => $slug,
            'approval_status' => 'approved',
            'status' => 'active',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main Branch',
            'slug' => $slug.'-main',
            'status' => 'active',
            'is_active' => true,
        ]);

        return [$owner, $gym, $branch];
    }

    private function memberFixture(Gym $gym, Branch $branch): User
    {
        $member = User::factory()->create(['name' => 'Visible Atlas Member', 'active_role' => RoleName::Member->value, 'is_active' => true]);
        $member->assignRole(RoleName::Member->value);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return $member;
    }

    private function connectedAccount(?Gym $gym, User $actor, string $wabaId): array
    {
        $account = WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gym?->id,
            'waba_id' => $wabaId,
            'business_name' => $gym?->name ?? 'Gym Atlas Platform',
            'access_token' => 'encrypted-access-token',
            'status' => 'connected',
            'health_status' => 'healthy',
            'connected_by_user_id' => $actor->id,
            'connected_at' => now(),
            'last_synced_at' => now(),
        ]);
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'phone_number_id' => 'phone-'.$wabaId,
            'display_phone_number' => '+91 90000 00000',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $template = WhatsAppTemplate::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'provider_template_id' => 'template-'.$wabaId,
            'name' => 'membership_expiry_notice',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'components' => [['type' => 'BODY', 'text' => 'Your membership is expiring soon.']],
        ]);

        return [$account, $template];
    }
}
