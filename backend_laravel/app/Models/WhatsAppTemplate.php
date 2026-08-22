<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'whatsapp_business_account_id',
        'provider_template_id',
        'name',
        'language',
        'category',
        'status',
        'quality_rating',
        'components',
        'metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBusinessAccount::class, 'whatsapp_business_account_id');
    }
}
