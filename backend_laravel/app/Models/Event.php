<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope', 'gym_id', 'branch_id', 'created_by_user_id', 'host_user_id', 'title', 'category',
        'description', 'cover_image_url', 'starts_at', 'ends_at', 'timezone', 'booking_opens_at',
        'booking_closes_at', 'cancellation_closes_at', 'capacity', 'waitlist_enabled', 'pricing_type',
        'price_amount', 'currency', 'payment_note', 'location_name', 'address', 'latitude', 'longitude',
        'status', 'published_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'booking_opens_at' => 'datetime',
            'booking_closes_at' => 'datetime', 'cancellation_closes_at' => 'datetime',
            'published_at' => 'datetime', 'cancelled_at' => 'datetime', 'waitlist_enabled' => 'boolean',
            'capacity' => 'integer', 'price_amount' => 'decimal:2', 'latitude' => 'float', 'longitude' => 'float',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(EventBooking::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
