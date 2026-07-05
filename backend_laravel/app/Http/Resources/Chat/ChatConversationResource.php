<?php

namespace App\Http\Resources\Chat;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $viewerIsTrainer = $viewer?->id === $this->trainer_id;
        $peer = $viewerIsTrainer ? $this->member : $this->trainer;
        $unreadCount = $viewerIsTrainer
            ? $this->trainer_unread_count
            : $this->member_unread_count;

        return [
            'id' => $this->room,
            'room' => $this->room,
            'trainer_id' => $this->trainer_id,
            'member_id' => $this->member_id,
            'peer' => $peer instanceof User ? [
                'id' => $peer->id,
                'name' => $peer->name,
                'email' => $peer->email,
                'avatar' => $peer->avatar,
            ] : null,
            'last_message' => $this->lastMessage
                ? ChatMessageResource::make($this->lastMessage)->resolve($request)
                : null,
            'last_message_body' => $this->last_message_body,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'last_sender_id' => $this->last_sender_id,
            'unread_count' => $unreadCount,
            'updated_at' => ($this->last_message_at ?? $this->updated_at)?->toIso8601String(),
        ];
    }
}
