<?php

namespace App\Services\Realtime;

use App\Jobs\PublishRealtimeEvent;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function isUserActiveInChat(string $room, int $userId): bool
    {
        if (! $this->configured()) {
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'X-Internal-Api-Key' => (string) config('services.realtime.internal_api_key'),
                ])
                ->connectTimeout(1)
                ->timeout(2)
                ->post(rtrim((string) config('services.realtime.url'), '/').'/internal/chat/active-status', [
                    'room' => $room,
                    'userId' => $userId,
                ]);

            return $response->successful()
                && $response->json('data.active') === true;
        } catch (\Throwable $exception) {
            Log::info('Realtime chat focus check unavailable; chat push will be sent.', [
                'room' => $room,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function configured(): bool
    {
        return (string) config('services.realtime.url') !== ''
            && (string) config('services.realtime.internal_api_key') !== '';
    }
}
