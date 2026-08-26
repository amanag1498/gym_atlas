<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricMemberLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'branch_id', 'biometric_device_id', 'member_profile_id', 'requested_by_user_id',
        'external_user_id', 'modalities', 'enrollment_method', 'status', 'sync_error',
        'enrolled_at', 'last_synced_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'modalities' => 'array',
            'enrolled_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    public function memberProfile(): BelongsTo
    {
        return $this->belongsTo(MemberProfile::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BiometricDeviceEvent::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(BiometricDeviceCommand::class);
    }
}
