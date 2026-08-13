<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->relationLoaded('bookings') ? $this->bookings->firstWhere('user_id', $request->user()?->id) : null;
        $reserved = (int) ($this->reserved_count ?? 0);
        $bookingOpen = $this->status === 'published'
            && $this->starts_at?->isFuture()
            && (! $this->booking_opens_at || now()->gte($this->booking_opens_at))
            && (! $this->booking_closes_at || now()->lte($this->booking_closes_at));
        $fullWithoutWaitlist = $this->capacity !== null && $reserved >= $this->capacity && ! $this->waitlist_enabled;
        $hasActiveBooking = $booking && in_array($booking->status, ['reserved', 'waitlisted', 'attended'], true);

        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'gym_id' => $this->gym_id,
            'branch_id' => $this->branch_id,
            'gym' => $this->whenLoaded('gym', fn () => $this->gym ? ['id' => $this->gym->id, 'name' => $this->gym->name] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'host' => $this->whenLoaded('host', fn () => $this->host ? ['id' => $this->host->id, 'name' => $this->host->name, 'avatar' => $this->host->avatar] : null),
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'booking_opens_at' => $this->booking_opens_at?->toIso8601String(),
            'booking_closes_at' => $this->booking_closes_at?->toIso8601String(),
            'cancellation_closes_at' => $this->cancellation_closes_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'reserved_count' => $reserved,
            'available_spots' => $this->capacity === null ? null : max(0, $this->capacity - $reserved),
            'waitlist_enabled' => $this->waitlist_enabled,
            'pricing_type' => $this->pricing_type,
            'price_amount' => $this->price_amount === null ? null : (float) $this->price_amount,
            'currency' => $this->currency,
            'payment_note' => $this->payment_note,
            'location_name' => $this->location_name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'can_book' => $bookingOpen && ! $fullWithoutWaitlist && ! $hasActiveBooking,
            'can_cancel_booking' => $booking && in_array($booking->status, ['reserved', 'waitlisted'], true)
                && $this->status === 'published'
                && $this->starts_at?->isFuture()
                && (! $this->cancellation_closes_at || now()->lte($this->cancellation_closes_at)),
            'attendance_open' => in_array($this->status, ['published', 'completed'], true)
                && now()->between($this->starts_at->copy()->subHours(2), $this->ends_at->copy()->addDay()),
            'booking' => $booking ? [
                'id' => $booking->id,
                'status' => $booking->status,
                'booked_at' => $booking->booked_at?->toIso8601String(),
            ] : null,
        ];
    }
}
