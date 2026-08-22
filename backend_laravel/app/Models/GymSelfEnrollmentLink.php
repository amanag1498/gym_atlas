<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymSelfEnrollmentLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'token',
        'name',
        'is_active',
        'rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rotated_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(GymSelfEnrollmentSubmission::class);
    }
}
