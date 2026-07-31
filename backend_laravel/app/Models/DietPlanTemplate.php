<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietPlanTemplate extends Model
{
    protected $fillable = ['created_by_user_id', 'name', 'goal', 'daily_calorie_target', 'protein_target_g', 'carbs_target_g', 'fats_target_g', 'dietary_preferences', 'allergies_and_restrictions', 'notes', 'meals', 'status'];

    protected function casts(): array
    {
        return ['meals' => 'array', 'protein_target_g' => 'decimal:2', 'carbs_target_g' => 'decimal:2', 'fats_target_g' => 'decimal:2'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeGlobalCatalog(Builder $query): Builder
    {
        return $query->where(function (Builder $scope): void {
            $scope
                ->whereNull('created_by_user_id')
                ->orWhereHas(
                    'creator.roles',
                    fn (Builder $roles) => $roles->where(
                        'name',
                        RoleName::PlatformAdmin->value,
                    ),
                );
        });
    }

    public function isGlobalCatalog(): bool
    {
        if ($this->created_by_user_id === null) {
            return true;
        }

        return $this->creator()
            ->whereHas(
                'roles',
                fn (Builder $roles) => $roles->where(
                    'name',
                    RoleName::PlatformAdmin->value,
                ),
            )
            ->exists();
    }
}
