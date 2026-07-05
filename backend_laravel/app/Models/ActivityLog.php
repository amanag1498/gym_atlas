<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'user_id',
        'gym_id',
        'branch_id',
        'event',
        'action',
        'actor_role',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'context',
        'old_values',
        'new_values',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
