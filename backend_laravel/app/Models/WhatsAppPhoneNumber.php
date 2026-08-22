<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppPhoneNumber extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'whatsapp_business_account_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'code_verification_status',
        'is_primary',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBusinessAccount::class, 'whatsapp_business_account_id');
    }
}
