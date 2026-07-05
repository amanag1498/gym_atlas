<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\ChatMessage;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\Notification;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatFcmNotificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_chat_message_sends_fcm_push_to_member_app_token(): void
    {
        $this->enableFcm();
        [$trainer, $member] = $this->assignedTrainerPair();

        UserFcmToken::query()->create([
            'user_id' => $member->id,
            'token' => 'member-fcm-token',
            'platform' => 'android',
            'app_role' => RoleName::Member->value,
        ]);

        $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/chat/messages', [
                'recipient_id' => $member->id,
                'message' => 'Please update your workout after the session.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return str_contains((string) $request->url(), '/messages:send')
                && $payload['message']['token'] === 'member-fcm-token'
                && $payload['message']['notification']['title'] === 'Coach Sparsh sent you a message'
                && $payload['message']['data']['type'] === 'chat_message'
                && $payload['message']['data']['click_action'] === 'FLUTTER_NOTIFICATION_CLICK';
        });
    }

    public function test_realtime_internal_chat_message_sends_fcm_push_to_trainer_app_token(): void
    {
        $this->enableFcm();
        [$trainer, $member] = $this->assignedTrainerPair();

        UserFcmToken::query()->create([
            'user_id' => $trainer->id,
            'token' => 'trainer-fcm-token',
            'platform' => 'android',
            'app_role' => RoleName::Trainer->value,
        ]);

        $this->postJson('/api/internal/chat/messages', [
            'room' => "trainer:{$trainer->id}:member:{$member->id}",
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'sender_id' => $member->id,
            'recipient_id' => $trainer->id,
            'message' => 'I finished today workout.',
            'client_message_id' => 'member-message-1',
        ], [
            'X-Internal-Api-Key' => config('services.realtime.internal_api_key'),
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();

            return str_contains((string) $request->url(), '/messages:send')
                && $payload['message']['token'] === 'trainer-fcm-token'
                && $payload['message']['notification']['title'] === 'Member Devendra sent you a message'
                && $payload['message']['data']['type'] === 'chat_message'
                && $payload['message']['data']['room'] !== '';
        });
    }

    public function test_chat_message_client_id_is_idempotent_for_rest_fallback(): void
    {
        $this->enableFcm();
        [$trainer, $member] = $this->assignedTrainerPair();

        $payload = [
            'recipient_id' => $member->id,
            'message' => 'Same message should not duplicate.',
            'client_message_id' => 'trainer-idempotent-1',
        ];

        $first = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/chat/messages', $payload)
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($trainer, 'sanctum')
            ->postJson('/api/chat/messages', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ChatMessage::query()->where('client_message_id', 'trainer-idempotent-1')->count());
        $this->assertSame(1, Notification::query()->where('data->room', "trainer:{$trainer->id}:member:{$member->id}")->count());
    }

    public function test_internal_chat_message_client_id_is_idempotent_for_socket_retry(): void
    {
        $this->enableFcm();
        [$trainer, $member] = $this->assignedTrainerPair();

        $payload = [
            'room' => "trainer:{$trainer->id}:member:{$member->id}",
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'sender_id' => $member->id,
            'recipient_id' => $trainer->id,
            'message' => 'Socket retry should not duplicate.',
            'client_message_id' => 'member-idempotent-1',
        ];

        $first = $this->postJson('/api/internal/chat/messages', $payload, [
            'X-Internal-Api-Key' => config('services.realtime.internal_api_key'),
        ])
            ->assertCreated()
            ->json('data.id');

        $second = $this->postJson('/api/internal/chat/messages', $payload, [
            'X-Internal-Api-Key' => config('services.realtime.internal_api_key'),
        ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ChatMessage::query()->where('client_message_id', 'member-idempotent-1')->count());
        $this->assertSame(1, Notification::query()->where('data->room', "trainer:{$trainer->id}:member:{$member->id}")->count());
    }

    public function test_internal_chat_message_can_suppress_fcm_when_recipient_is_online(): void
    {
        $this->enableFcm();
        [$trainer, $member] = $this->assignedTrainerPair();

        UserFcmToken::query()->create([
            'user_id' => $trainer->id,
            'token' => 'trainer-online-fcm-token',
            'platform' => 'android',
            'app_role' => RoleName::Trainer->value,
        ]);

        $this->postJson('/api/internal/chat/messages', [
            'room' => "trainer:{$trainer->id}:member:{$member->id}",
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'sender_id' => $member->id,
            'recipient_id' => $trainer->id,
            'message' => 'You should see this over socket only.',
            'client_message_id' => 'member-online-1',
            'suppress_push' => true,
        ], [
            'X-Internal-Api-Key' => config('services.realtime.internal_api_key'),
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertSame(1, ChatMessage::query()->where('client_message_id', 'member-online-1')->count());
        $this->assertSame(1, Notification::query()->where('data->room', "trainer:{$trainer->id}:member:{$member->id}")->count());
        Http::assertSentCount(0);
    }

    private function enableFcm(): void
    {
        config()->set('services.firebase.project_id', 'gym-atlas-test');
        config()->set('services.firebase.service_account_json', json_encode([
            'client_email' => 'firebase-adminsdk-test@gym-atlas-test.iam.gserviceaccount.com',
            'private_key' => 'unused-in-test',
        ]));

        Cache::put(
            'firebase_messaging_access_token:firebase-adminsdk-test@gym-atlas-test.iam.gserviceaccount.com',
            'test-access-token',
            now()->addMinutes(10)
        );

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/gym-atlas-test/messages/1']),
        ]);
    }

    private function assignedTrainerPair(): array
    {
        $gym = Gym::query()->create([
            'name' => 'Iron Core Fitness',
            'slug' => 'iron-core-test',
            'status' => 'active',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'HSR Branch',
            'slug' => 'iron-core-hsr-test',
            'status' => 'active',
        ]);
        $trainer = User::factory()->create([
            'name' => 'Coach Sparsh',
            'email' => 'coach-sparsh@example.test',
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $member = User::factory()->create([
            'name' => 'Member Devendra',
            'email' => 'member-devendra@example.test',
            'active_role' => RoleName::Member->value,
            'is_active' => true,
        ]);

        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'specializations' => ['Strength'],
            'experience_years' => 4,
            'is_active' => true,
        ]);
        MemberProfile::query()->create([
            'user_id' => $member->id,
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'assigned_trainer_user_id' => $trainer->id,
            'membership_status' => 'active',
            'is_active' => true,
        ]);

        return [$trainer, $member];
    }
}
