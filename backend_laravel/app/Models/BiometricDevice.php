<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'branch_id', 'created_by_user_id', 'uuid', 'name', 'vendor', 'model',
        'firmware_version', 'connector_version', 'serial_number', 'adapter_key', 'connection_method', 'modalities',
        'capabilities', 'configuration', 'secret_hash', 'status', 'is_active', 'last_seen_at',
        'last_event_at', 'clock_skew_seconds', 'last_error',
    ];

    protected $hidden = ['secret_hash', 'configuration'];

    protected function casts(): array
    {
        return [
            'modalities' => 'array',
            'capabilities' => 'array',
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_event_at' => 'datetime',
            'clock_skew_seconds' => 'integer',
        ];
    }

    public function effectiveStatus(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        if ($this->last_seen_at === null) {
            return $this->status === 'error' ? 'error' : 'pending';
        }

        if ($this->adapter_key === 'essl_ebioserver' && $this->status === 'connected') {
            return 'connected';
        }

        return $this->last_seen_at->lt(now()->subMinutes(5)) ? 'offline' : $this->status;
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberLinks(): HasMany
    {
        return $this->hasMany(BiometricMemberLink::class);
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
