<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationProfile extends Model
{
    protected $fillable = ['gym_id', 'branch_id', 'user_id', 'worker_type', 'monthly_salary', 'payout_day', 'effective_from', 'effective_until', 'is_active'];

    protected function casts(): array
    {
        return ['monthly_salary' => 'decimal:2', 'payout_day' => 'integer', 'effective_from' => 'date', 'effective_until' => 'date', 'is_active' => 'boolean'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
