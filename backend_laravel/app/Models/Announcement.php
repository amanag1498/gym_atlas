<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'created_by',
        'audience_type',
        'title',
        'message',
        'status',
        'is_platform_wide',
        'send_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_platform_wide' => 'boolean',
            'status' => 'string',
            'send_at' => 'datetime',
            'metadata' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }
}
