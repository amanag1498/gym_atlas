<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'logged_by_user_id',
        'log_date',
        'weight_kg',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'weight_kg' => 'decimal:2',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }
}
