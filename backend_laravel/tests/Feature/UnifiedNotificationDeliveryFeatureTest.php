<?php

namespace Tests\Feature;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationTransport;
use App\Jobs\DeliverNotificationOutbox;
use App\Jobs\PublishRealtimeEvent;
use App\Models\CommunicationAutomationRule;
use App\Models\CommunicationOutbox;
use App\Models\NotificationChannelPreference;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class UnifiedNotificationDeliveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_notification_records_feed_delivery_and_outbox_atomically(): void
    {
        Queue::fake();
        $user = User::factory()->create(['active_role' => 'member']);

        $notification = app(NotificationService::class)->create(
            user: $user,
            type: 'gym_announcement',
            title: 'Schedule update',
            body: 'The evening batch starts at 6 PM.',
            data: ['route' => '/notifications', 'app_role' => 'member'],
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Database->value,
            'status' => NotificationDeliveryStatus::Delivered->value,
            'target_count' => 1,
            'success_count' => 1,
        ]);
        $this->assertDatabaseHas('communication_outbox', [
            'aggregate_id' => $notification->id,
            'idempotency_key' => 'notification:'.$notification->id.':deliver',
            'status' => 'pending',
        ]);
    }

    public function test_outbox_sends_realtime_and_firebase_once_even_when_job_is_replayed(): void
    {
        Queue::fake();
        $user = User::factory()->create(['active_role' => 'member']);
        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => 'member-device-token',
            'platform' => 'android',
            'app_role' => 'member',
            'last_seen_at' => now(),
        ]);
        $notification = app(NotificationService::class)->create(
            user: $user,
            type: 'gym_announcement',
            title: 'Schedule update',
            body: 'The evening batch starts at 6 PM.',
            data: ['route' => '/notifications', 'app_role' => 'member'],
        );
        $outbox = CommunicationOutbox::query()->where('aggregate_id', $notification->id)->firstOrFail();

        $fcm = Mockery::mock(FcmNotificationService::class);
        $fcm->shouldReceive('isConfigured')->once()->andReturnTrue();
        $fcm->shouldReceive('sendToUser')
            ->once()
            ->withArgs(fn (User $recipient, string $title, string $body, array $data, string $appRole): bool => $recipient->is($user)
                && $title === 'Schedule update'
                && $body === 'The evening batch starts at 6 PM.'
                && $data['notification_id'] === $notification->id
                && $appRole === 'member')
            ->andReturn(1);

        $job = new DeliverNotificationOutbox($outbox->id);
        $job->handle($fcm);
        $job->handle($fcm);

        Queue::assertPushed(PublishRealtimeEvent::class, 1);
        $this->assertDatabaseHas('communication_outbox', [
            'id' => $outbox->id,
            'status' => 'processed',
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Firebase->value,
            'status' => NotificationDeliveryStatus::Sent->value,
            'success_count' => 1,
        ]);
        $this->assertSame(3, NotificationDelivery::query()->where('notification_id', $notification->id)->count());
    }

    public function test_missing_device_token_is_recorded_without_losing_in_app_notification(): void
    {
        Queue::fake();
        $user = User::factory()->create(['active_role' => 'admin']);
        $notification = app(NotificationService::class)->create(
            user: $user,
            type: 'operational_alert',
            title: 'Daily summary',
            body: 'Your daily summary is ready.',
            data: ['app_role' => 'admin'],
        );
        $outbox = CommunicationOutbox::query()->where('aggregate_id', $notification->id)->firstOrFail();
        $fcm = Mockery::mock(FcmNotificationService::class);
        $fcm->shouldNotReceive('isConfigured');
        $fcm->shouldNotReceive('sendToUser');

        (new DeliverNotificationOutbox($outbox->id))->handle($fcm);

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Firebase->value,
            'status' => NotificationDeliveryStatus::Skipped->value,
            'error_code' => 'no_registered_device',
        ]);
    }

    public function test_read_state_is_reflected_in_database_delivery(): void
    {
        Queue::fake();
        $user = User::factory()->create(['active_role' => 'member']);
        $notification = app(NotificationService::class)->create(
            user: $user,
            type: 'gym_announcement',
            title: 'Welcome',
            body: 'Welcome to your gym.',
        );

        app(NotificationService::class)->markRead($notification);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => NotificationTransport::Database->value,
            'status' => NotificationDeliveryStatus::Read->value,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_whatsapp_and_in_app_preferences_are_independent(): void
    {
        Queue::fake();
        $user = User::factory()->create(['active_role' => 'member']);
        CommunicationAutomationRule::query()->create([
            'notification_type' => 'gym_announcement',
            'recipient_role' => 'member',
            'in_app_enabled' => false,
            'whatsapp_enabled' => true,
            'is_enabled' => true,
            'configuration' => [],
            'created_by_user_id' => $user->id,
        ]);
        $notification = app(NotificationService::class)->create(
            user: $user,
            type: 'gym_announcement',
            title: 'WhatsApp only',
            body: 'This must stay out of the in-app feed.',
        );

        $this->assertNotNull($notification);
        $this->assertFalse($notification->in_app_visible);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'transport' => 'database',
            'status' => 'skipped',
            'error_code' => 'automation_channel_disabled',
        ]);
    }

    public function test_abandoned_outbox_lock_is_redispatched(): void
    {
        Queue::fake();
        $outbox = CommunicationOutbox::query()->create([
            'event_type' => 'notification.created',
            'aggregate_type' => 'notification',
            'aggregate_id' => 999,
            'idempotency_key' => 'stale-outbox-test',
            'status' => 'processing',
            'attempt_count' => 1,
            'available_at' => now()->subHour(),
            'locked_at' => now()->subMinutes(11),
        ]);

        $this->artisan('communications:dispatch-outbox')->assertSuccessful();

        Queue::assertPushed(DeliverNotificationOutbox::class, fn ($job): bool => $job->outboxId === $outbox->id);
    }
}
