<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDelivery extends Model
{
    protected $fillable = ['gym_id', 'recipient_email', 'category', 'subject', 'status', 'error_message', 'metadata', 'sent_at'];
    protected function casts(): array { return ['metadata' => 'array', 'sent_at' => 'datetime']; }
    public function gym(): BelongsTo { return $this->belongsTo(Gym::class); }
}
