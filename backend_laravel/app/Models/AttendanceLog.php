<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'checked_in_by',
        'check_in_method',
        'checked_in_at',
        'last_presence_at',
        'checked_out_at',
        'attendance_window_ends_at',
        'notes',
        'source_device',
        'scan_reference_hash',
        'biometric_device_id',
        'biometric_device_event_id',
        'smart_attendance_hub_id',
        'smart_attendance_detection',
        'occurred_at_device',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'last_presence_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'attendance_window_ends_at' => 'datetime',
            'occurred_at_device' => 'datetime',
            'received_at' => 'datetime',
            'smart_attendance_detection' => 'array',
        ];
    }

    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    public function biometricDeviceEvent(): BelongsTo
    {
        return $this->belongsTo(BiometricDeviceEvent::class);
    }

    public function smartAttendanceHub(): BelongsTo
    {
        return $this->belongsTo(SmartAttendanceHub::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function memberProfile(): BelongsTo
    {
        return $this->belongsTo(MemberProfile::class, 'member_id', 'user_id');
    }

    public function memberMemberships(): HasMany
    {
        return $this->hasMany(MemberMembership::class, 'member_id', 'member_id');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
