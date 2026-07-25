<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DietPlan extends Model
{
    protected $fillable = ['gym_id', 'branch_id', 'member_id', 'trainer_id', 'created_by_user_id', 'name', 'goal', 'daily_calorie_target', 'protein_target_g', 'carbs_target_g', 'fats_target_g', 'dietary_preferences', 'allergies_and_restrictions', 'notes', 'status', 'assigned_at', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['protein_target_g' => 'decimal:2', 'carbs_target_g' => 'decimal:2', 'fats_target_g' => 'decimal:2', 'assigned_at' => 'datetime', 'starts_on' => 'date', 'ends_on' => 'date'];
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

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(DietPlanMeal::class)->orderBy('sort_order');
    }
}
