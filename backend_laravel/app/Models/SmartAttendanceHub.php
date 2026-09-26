<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartAttendanceHub extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'public_id',
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'name',
        'platform',
        'device_secret_hash',
        'status',
        'is_active',
        'last_seen_at',
        'firmware_version',
        'metadata',
    ];

    protected $hidden = ['device_secret_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function effectiveStatus(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        if ($this->last_seen_at === null) {
            return $this->status === 'disabled' ? 'disabled' : 'pending';
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

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
