<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DietPlanMeal extends Model
{
    protected $fillable = ['diet_plan_id', 'name', 'meal_type', 'scheduled_time', 'sort_order', 'calories', 'protein_g', 'carbs_g', 'fats_g', 'notes'];

    protected function casts(): array
    {
        return ['protein_g' => 'decimal:2', 'carbs_g' => 'decimal:2', 'fats_g' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DietPlan::class, 'diet_plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DietPlanMealItem::class)->orderBy('sort_order');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DietMealLog::class);
    }
}
