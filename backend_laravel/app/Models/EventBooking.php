<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventBooking extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'user_id', 'status', 'booked_at', 'cancelled_at', 'promoted_at',
        'checked_in_at', 'checked_in_by_user_id', 'cancellation_reason', 'price_amount_snapshot',
        'currency_snapshot', 'payment_note_snapshot'];

    protected function casts(): array
    {
        return ['booked_at' => 'datetime', 'cancelled_at' => 'datetime', 'promoted_at' => 'datetime',
            'checked_in_at' => 'datetime', 'price_amount_snapshot' => 'decimal:2'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
