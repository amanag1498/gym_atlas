<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietPlanMealItem extends Model
{
    protected $fillable = ['diet_plan_meal_id', 'name', 'quantity', 'sort_order', 'calories', 'protein_g', 'carbs_g', 'fats_g', 'notes'];

    protected function casts(): array
    {
        return ['protein_g' => 'decimal:2', 'carbs_g' => 'decimal:2', 'fats_g' => 'decimal:2'];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(DietPlanMeal::class, 'diet_plan_meal_id');
    }
}
