<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodCatalogItem extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'name',
        'category',
        'default_quantity',
        'serving_size_g',
        'calories',
        'protein_g',
        'carbs_g',
        'fats_g',
        'fiber_g',
        'dietary_tags',
        'allergens',
        'notes',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'serving_size_g' => 'decimal:2',
            'protein_g' => 'decimal:2',
            'carbs_g' => 'decimal:2',
            'fats_g' => 'decimal:2',
            'fiber_g' => 'decimal:2',
            'dietary_tags' => 'array',
            'allergens' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function dietMealItems(): HasMany
    {
        return $this->hasMany(DietPlanMealItem::class);
    }
}
