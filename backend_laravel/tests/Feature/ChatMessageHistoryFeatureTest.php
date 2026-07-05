<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageHistoryFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_messages_are_loaded_with_keyset_cursor_metadata(): void
    {
        [$trainer, $member] = $this->assignedTrainerPair();
        $room = "trainer:{$trainer->id}:member:{$member->id}";

        $oldest = $this->createMessage($room, $trainer, $member, $trainer->id, $member->id, 'Oldest message', now()->subMinutes(3), 'm-1');
        $middle = $this->createMessage($room, $trainer, $member, $member->id, $trainer->id, 'Middle message', now()->subMinutes(2), 'm-2');
        $latest = $this->createMessage($room, $trainer, $member, $trainer->id, $member->id, 'Latest message', now()->subMinute(), 'm-3');

        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/chat/messages?recipient_id={$member->id}&per_page=2")
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $middle->id)
            ->assertJsonPath('data.1.id', (string) $latest->id)
            ->assertJsonPath('meta.cursor.has_more', true)
            ->assertJsonPath('meta.cursor.next_before_id', $middle->id)
            ->assertJsonPath('meta.retention.days', 365);

        $this->actingAs($trainer, 'sanctum')
            ->getJson("/api/chat/messages?recipient_id={$member->id}&per_page=2&before_id={$middle->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $oldest->id)
            ->assertJsonPath('meta.cursor.has_more', false)
            ->assertJsonPath('meta.cursor.next_before_id', null);
    }

    public function test_chat_prune_removes_expired_messages_and_repairs_conversation_summary(): void
    {
        [$trainer, $member] = $this->assignedTrainerPair();
        $room = "trainer:{$trainer->id}:member:{$member->id}";

        $expired = $this->createMessage($room, $trainer, $member, $member->id, $trainer->id, 'Expired message', now()->subDays(40), 'expired-1');
        $fresh = $this->createMessage($room, $trainer, $member, $trainer->id, $member->id, 'Fresh message', now()->subDays(2), 'fresh-1');

        ChatConversation::query()->create([
            'room' => $room,
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'last_message_id' => $expired->id,
            'last_message_body' => $expired->body,
            'last_sender_id' => $expired->sender_id,
            'last_message_at' => $expired->created_at,
            'trainer_unread_count' => 1,
            'member_unread_count' => 1,
        ]);

        $this->artisan('chat:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('chat_messages', ['id' => $expired->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $fresh->id]);

        $conversation = ChatConversation::query()->where('room', $room)->firstOrFail();
        $this->assertSame($fresh->id, $conversation->last_message_id);
        $this->assertSame('Fresh message', $conversation->last_message_body);
        $this->assertSame(0, $conversation->trainer_unread_count);
        $this->assertSame(1, $conversation->member_unread_count);
    }

    private function createMessage(
        string $room,
        User $trainer,
        User $member,
        int $senderId,
        int $recipientId,
        string $body,
        \DateTimeInterface $createdAt,
        string $clientMessageId,
    ): ChatMessage {
        $message = ChatMessage::query()->create([
            'room' => $room,
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'body' => $body,
            'client_message_id' => $clientMessageId,
            'delivery_status' => 'sent',
            'delivered_at' => $createdAt,
        ]);

        $message->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $message;
    }

    private function assignedTrainerPair(): array
    {
        $gym = Gym::query()->create([
            'name' => 'Iron Core Fitness',
            'slug' => 'iron-core-history-test',
            'status' => 'active',
        ]);
        $branch = Branch::query()->create([
            'gym_id' => $gym->id,
            'name' => 'HSR Branch',
            'slug' => 'iron-core-history-hsr-test',
            'status' => 'active',
        ]);
        $trainer = User::factory()->create([
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $member = User::factory()->create([
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
