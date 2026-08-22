<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'branch_id', 'name', 'audience_type', 'audience_filters', 'status',
        'scheduled_for', 'started_at', 'completed_at', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'audience_filters' => 'array',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(CommunicationCampaignChannel::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class);
    }
}
