<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDeviceCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'biometric_device_id', 'biometric_member_link_id', 'command_type', 'payload', 'status',
        'attempts', 'available_at', 'dispatched_at', 'completed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function memberLink(): BelongsTo
    {
        return $this->belongsTo(BiometricMemberLink::class, 'biometric_member_link_id');
    }
}
