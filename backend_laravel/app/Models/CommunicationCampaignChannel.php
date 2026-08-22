<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaignChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_campaign_id', 'channel', 'notification_type', 'title',
        'body', 'whatsapp_template_id', 'template_parameters',
    ];

    protected function casts(): array
    {
        return ['template_parameters' => 'array'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
