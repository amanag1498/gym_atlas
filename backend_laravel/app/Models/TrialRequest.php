<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'request_type',
        'source',
        'name',
        'phone',
        'email',
        'preferred_date',
        'preferred_time',
        'status',
        'assigned_trainer_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
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

    public function assignedTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_trainer_id');
    }
}
