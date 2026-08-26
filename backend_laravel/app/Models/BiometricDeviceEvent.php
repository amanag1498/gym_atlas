<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDeviceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'branch_id', 'biometric_device_id', 'biometric_member_link_id', 'attendance_log_id',
        'vendor_event_id', 'payload_hash', 'external_user_id', 'event_type', 'modality', 'direction',
        'occurred_at_device', 'received_at', 'normalized_payload', 'status', 'attempts', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at_device' => 'datetime',
            'received_at' => 'datetime',
            'normalized_payload' => 'array',
            'attempts' => 'integer',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function memberLink(): BelongsTo
    {
        return $this->belongsTo(BiometricMemberLink::class, 'biometric_member_link_id');
    }

    public function attendanceLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
