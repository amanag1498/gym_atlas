<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietMealLog extends Model
{
    protected $fillable = ['diet_plan_meal_id', 'member_id', 'logged_for', 'completed_at', 'notes'];

    protected function casts(): array
    {
        return ['logged_for' => 'date', 'completed_at' => 'datetime'];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(DietPlanMeal::class, 'diet_plan_meal_id');
    }
}
