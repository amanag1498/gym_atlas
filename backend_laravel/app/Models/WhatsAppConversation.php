<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'whatsapp_business_account_id', 'whatsapp_phone_number_id', 'user_id',
        'contact_wa_id', 'contact_name', 'status', 'service_window_expires_at',
        'assigned_to_user_id', 'last_message_at',
    ];

    protected function casts(): array
    {
        return ['service_window_expires_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }
}
