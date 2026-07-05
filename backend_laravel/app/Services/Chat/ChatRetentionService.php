<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ChatRetentionService
{
    public function pruneOlderThan(CarbonInterface $cutoff): int
    {
        $rooms = ChatMessage::query()
            ->where('created_at', '<', $cutoff)
            ->distinct()
            ->pluck('room');

        if ($rooms->isEmpty()) {
            return 0;
        }

        $deleted = 0;

        ChatMessage::query()
            ->where('created_at', '<', $cutoff)
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $messages) use (&$deleted): void {
                $ids = $messages->pluck('id')->all();
                $deleted += ChatMessage::query()->whereIn('id', $ids)->delete();
            });

        $this->refreshConversations($rooms);

        return $deleted;
    }

    private function refreshConversations(Collection $rooms): void
    {
        ChatConversation::query()
            ->whereIn('room', $rooms->all())
            ->each(function (ChatConversation $conversation): void {
                $lastMessage = ChatMessage::query()
                    ->where('room', $conversation->room)
                    ->orderByDesc('id')
                    ->first();

                $trainerUnreadCount = ChatMessage::query()
                    ->where('room', $conversation->room)
                    ->where('recipient_id', $conversation->trainer_id)
                    ->whereNull('read_at')
                    ->count();

                $memberUnreadCount = ChatMessage::query()
                    ->where('room', $conversation->room)
                    ->where('recipient_id', $conversation->member_id)
                    ->whereNull('read_at')
                    ->count();

                $conversation->forceFill([
                    'last_message_id' => $lastMessage?->id,
                    'last_message_body' => $lastMessage?->body,
                    'last_sender_id' => $lastMessage?->sender_id,
                    'last_message_at' => $lastMessage?->created_at,
                    'trainer_unread_count' => $trainerUnreadCount,
                    'member_unread_count' => $memberUnreadCount,
                ])->save();
            });
    }
}
