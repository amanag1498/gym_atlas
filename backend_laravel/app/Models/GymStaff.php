<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GymStaff extends Model
{
    use HasFactory;

    protected $table = 'gym_user';

    protected $fillable = [
        'gym_id',
        'user_id',
        'branch_id',
        'role_name',
        'custom_permissions',
        'permissions',
        'status',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'custom_permissions' => 'array',
            'permissions' => 'array',
            'is_primary' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
