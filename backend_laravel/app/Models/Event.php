<?php

namespace App\Models;

use App\Support\Media\StoredImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope', 'booking_audience', 'app_visibility', 'public_booking_enabled', 'registration_form_schema',
        'public_token', 'public_link_rotated_at', 'gym_id', 'branch_id', 'created_by_user_id', 'host_user_id', 'title', 'category',
        'description', 'cover_image_path', 'cover_image_url', 'starts_at', 'ends_at', 'timezone', 'booking_opens_at',
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
            'public_booking_enabled' => 'boolean', 'registration_form_schema' => 'array', 'public_link_rotated_at' => 'datetime',
            'capacity' => 'integer', 'price_amount' => 'decimal:2', 'latitude' => 'float', 'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->public_token ??= (string) Str::uuid();
            $event->booking_audience ??= $event->scope === 'global' ? 'atlas_members' : 'gym_members';
            $event->app_visibility ??= $event->scope === 'global' ? 'all_atlas' : 'hosting_gym';
        });
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

    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        return StoredImage::publicUrl($this->cover_image_path, $value);
    }
}
