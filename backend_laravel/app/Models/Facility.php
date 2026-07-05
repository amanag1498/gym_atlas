<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'slug',
        'description',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class, 'facility_gym')
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_facility')
            ->withTimestamps();
    }
}
