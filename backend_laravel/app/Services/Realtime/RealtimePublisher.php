<?php

namespace App\Services\Realtime;

use App\Jobs\PublishRealtimeEvent;
use App\Models\ChatMessage;

class RealtimePublisher
{
    public function chatMessage(ChatMessage $message): void
    {
        if (! $this->configured()) {
            return;
        }

        PublishRealtimeEvent::dispatch('internal/chat/messages', [
            'room' => $message->room,
            'trainerId' => $message->trainer_id,
            'memberId' => $message->member_id,
            'message' => [
                'id' => (string) $message->id,
                'room' => $message->room,
                'senderId' => $message->sender_id,
                'recipientId' => $message->recipient_id,
                'body' => $message->body,
                'clientMessageId' => $message->client_message_id,
                'metadata' => $message->metadata ?? [],
                'createdAt' => $message->created_at?->toIso8601String(),
                'persisted' => true,
            ],
        ]);
    }

    public function chatRead(
        string $room,
        int $userId,
        int $recipientId,
        array $messageIds,
        string $readAt,
    ): void {
        if (! $this->configured() || $messageIds === []) {
            return;
        }

        foreach (array_chunk($messageIds, 1000) as $chunk) {
            PublishRealtimeEvent::dispatch('internal/chat/read', [
                'room' => $room,
                'userId' => $userId,
                'recipientId' => $recipientId,
                'messageIds' => array_map('strval', $chunk),
                'readAt' => $readAt,
            ]);
        }
    }

    private function configured(): bool
    {
        return (string) config('services.realtime.url') !== ''
            && (string) config('services.realtime.internal_api_key') !== '';
    }
}
