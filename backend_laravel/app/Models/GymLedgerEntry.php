<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'created_by_user_id',
        'source_type',
        'source_id',
        'entry_type',
        'direction',
        'category',
        'title',
        'description',
        'reference',
        'payment_mode',
        'amount',
        'status',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
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
}
