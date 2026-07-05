<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'branch_id',
        'member_id',
        'logged_by_user_id',
        'measured_on',
        'chest_cm',
        'waist_cm',
        'hips_cm',
        'arm_cm',
        'thigh_cm',
        'calf_cm',
        'body_fat_percentage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'chest_cm' => 'decimal:2',
            'waist_cm' => 'decimal:2',
            'hips_cm' => 'decimal:2',
            'arm_cm' => 'decimal:2',
            'thigh_cm' => 'decimal:2',
            'calf_cm' => 'decimal:2',
            'body_fat_percentage' => 'decimal:2',
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
