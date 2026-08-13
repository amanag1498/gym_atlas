<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'event_id' => $this->event_id, 'user_id' => $this->user_id,
            'status' => $this->status, 'booked_at' => $this->booked_at?->toIso8601String(),
            'promoted_at' => $this->promoted_at?->toIso8601String(), 'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'price_amount_snapshot' => $this->price_amount_snapshot === null ? null : (float) $this->price_amount_snapshot,
            'currency_snapshot' => $this->currency_snapshot,
            'payment_note_snapshot' => $this->payment_note_snapshot,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email, 'phone' => $this->user->phone, 'avatar' => $this->user->avatar]),
            'event' => EventResource::make($this->whenLoaded('event')),
        ];
    }
}
