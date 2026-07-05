<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'room' => $this->room,
            'trainer_id' => $this->trainer_id,
            'member_id' => $this->member_id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'body' => $this->body,
            'message' => $this->body,
            'client_message_id' => $this->client_message_id,
            'metadata' => $this->metadata ?? [],
            'delivery_status' => $this->delivery_status ?? 'sent',
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
