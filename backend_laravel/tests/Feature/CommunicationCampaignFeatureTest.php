<?php

namespace Tests\Feature;

use App\Jobs\DeliverCommunicationRecipient;
use App\Jobs\DeliverNotificationOutbox;
use App\Jobs\StartCommunicationCampaign;
use App\Models\CommunicationAutomationRule;
use App\Models\CommunicationOutbox;
use App\Models\CommunicationRecipient;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use App\Models\WhatsAppBusinessAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use App\Services\Communication\CommunicationCampaignService;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notification\NotificationService;
use App\Services\WhatsApp\MetaWhatsAppClient;
use App\Services\WhatsApp\WhatsAppConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CommunicationCampaignFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_app_campaign_freezes_audience_and_creates_one_notification_per_recipient(): void
    {
        Queue::fake();
        [$owner, $gym, $member] = $this->gymAndMember();
        $service = app(CommunicationCampaignService::class);
        $campaign = $service->create($gym, $owner, [
            'name' => 'Friday schedule',
            'audience_type' => 'selected_members',
            'member_ids' => [$member->id],
            'channels' => [
                'in_app' => [
                    'notification_type' => 'gym_announcement',
                    'title' => 'Friday schedule',
                    'body' => 'The gym closes at 8 PM this Friday.',
                ],
            ],
        ]);

        $this->assertSame(1, $service->preview($campaign)['by_channel']['in_app']['eligible']);
        $service->schedule($campaign);
        (new StartCommunicationCampaign($campaign->id))->handle($service);
        $recipient = CommunicationRecipient::query()->where('communication_campaign_id', $campaign->id)->firstOrFail();
        (new DeliverCommunicationRecipient($recipient->id))->handle(
            app(NotificationService::class),
            app(MetaWhatsAppClient::class),
        );

        $this->assertDatabaseHas('communication_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'title' => 'Friday schedule',
        ]);
        $this->assertDatabaseHas('communication_campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
        ]);
    }

    public function test_whatsapp_campaign_excludes_without_consent_then_sends_approved_template_after_opt_in(): void
    {
        Queue::fake();
        config()->set('services.meta_whatsapp', [
            'graph_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
            'default_country_code' => '91',
        ]);
        [$owner, $gym, $member] = $this->gymAndMember('+91 98765 43210');
        $account = WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'waba-campaign',
            'access_token' => 'encrypted-provider-token',
            'status' => 'connected',
            'health_status' => 'healthy',
        ]);
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'phone_number_id' => 'sender-phone-id',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $template = WhatsAppTemplate::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'provider_template_id' => 'template-utility',
            'name' => 'membership_expiry',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'components' => [[
                'type' => 'BODY',
                'text' => 'Hi {{1}}, {{2}}',
            ]],
        ]);
        $service = app(CommunicationCampaignService::class);
        $campaign = $service->create($gym, $owner, [
            'name' => 'Membership reminder',
            'audience_type' => 'selected_members',
            'member_ids' => [$member->id],
            'channels' => [
                'whatsapp' => [
                    'whatsapp_template_id' => $template->id,
                    'template_parameters' => ['{member_name}', 'Membership renewal reminder'],
                ],
            ],
        ]);

        $this->assertSame(1, $service->preview($campaign)['by_channel']['whatsapp']['exclusion_reasons']['consent_missing']);
        app(WhatsAppConsentService::class)->set($member, $gym, 'utility', true);
        $this->assertSame(1, $service->preview($campaign)['by_channel']['whatsapp']['eligible']);

        Http::fake([
            'https://graph.facebook.com/v23.0/sender-phone-id/messages' => Http::response([
                'messages' => [['id' => 'wamid.campaign-message']],
            ]),
        ]);
        $service->schedule($campaign);
        (new StartCommunicationCampaign($campaign->id))->handle($service);
        $recipient = CommunicationRecipient::query()->where('communication_campaign_id', $campaign->id)->firstOrFail();
        (new DeliverCommunicationRecipient($recipient->id))->handle(
            app(NotificationService::class),
            app(MetaWhatsAppClient::class),
        );

        Http::assertSent(fn ($request): bool => data_get($request->data(), 'template.components.0.parameters.0.text') === $member->name
            && data_get($request->data(), 'template.components.0.parameters.1.text') === 'Membership renewal reminder');

        $this->assertDatabaseHas('communication_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
            'provider_message_id' => 'wamid.campaign-message',
        ]);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.campaign-message',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
    }

    public function test_enabled_automation_adds_whatsapp_to_an_existing_lifecycle_notification(): void
    {
        Queue::fake();
        config()->set('services.meta_whatsapp', [
            'graph_url' => 'https://graph.facebook.com',
            'graph_version' => 'v23.0',
            'default_country_code' => '91',
        ]);
        [$owner, $gym, $member] = $this->gymAndMember('+91 98765 43210');
        $account = WhatsAppBusinessAccount::query()->create([
            'gym_id' => $gym->id,
            'waba_id' => 'waba-automation',
            'access_token' => 'automation-token',
            'status' => 'connected',
            'health_status' => 'healthy',
        ]);
        WhatsAppPhoneNumber::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'phone_number_id' => 'automation-sender',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $template = WhatsAppTemplate::query()->create([
            'whatsapp_business_account_id' => $account->id,
            'name' => 'payment_due',
            'language' => 'en_US',
            'category' => 'utility',
            'status' => 'approved',
            'components' => [[
                'type' => 'BODY',
                'text' => 'Hi {{1}}, {{2}}',
            ]],
        ]);
        app(WhatsAppConsentService::class)->set($member, $gym, 'utility', true);
        CommunicationAutomationRule::query()->create([
            'gym_id' => $gym->id,
            'notification_type' => 'payment_due',
            'recipient_role' => 'member',
            'in_app_enabled' => true,
            'whatsapp_enabled' => true,
            'whatsapp_template_id' => $template->id,
            'is_enabled' => true,
            'configuration' => [
                'template_parameter_values' => ['{member_name}', '{notification_message}'],
            ],
            'created_by_user_id' => $owner->id,
        ]);
        $notification = app(NotificationService::class)->create(
            user: $member,
            type: 'payment_due',
            title: 'Payment due',
            body: 'Your membership payment is due.',
            gymId: $gym->id,
            data: ['app_role' => 'member'],
        );
        $outbox = CommunicationOutbox::query()->where('aggregate_id', $notification->id)->firstOrFail();
        Http::fake([
            'https://graph.facebook.com/v23.0/automation-sender/messages' => Http::response([
                'messages' => [['id' => 'wamid.automated-message']],
            ]),
        ]);
        $fcm = Mockery::mock(FcmNotificationService::class);
        $fcm->shouldNotReceive('isConfigured');
        $fcm->shouldNotReceive('sendToUser');

        (new DeliverNotificationOutbox($outbox->id))->handle($fcm, app(MetaWhatsAppClient::class));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://graph.facebook.com/v23.0/automation-sender/messages'
            && data_get($request->data(), 'template.components.0.parameters.0.text') === $member->name
            && data_get($request->data(), 'template.components.0.parameters.1.text') === 'Your membership payment is due.');

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => 'whatsapp',
            'status' => 'sent',
            'provider_message_id' => 'wamid.automated-message',
        ]);
    }

    private function gymAndMember(?string $phone = null): array
    {
        $owner = User::factory()->create();
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Campaign Gym',
            'slug' => 'campaign-gym-'.str()->random(6),
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
        ]);
        $member = User::factory()->create(['phone' => $phone, 'active_role' => 'member', 'is_active' => true]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'status' => 'active',
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return [$owner, $gym, $member];
    }
}
