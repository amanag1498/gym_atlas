<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'communication_campaign_id', 'communication_campaign_channel_id', 'user_id',
        'channel', 'destination', 'status', 'exclusion_reason', 'provider_message_id',
        'recipient_snapshot', 'attempt_count', 'last_error', 'sent_at', 'delivered_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_snapshot' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'communication_campaign_id');
    }

    public function channelDefinition(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaignChannel::class, 'communication_campaign_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
