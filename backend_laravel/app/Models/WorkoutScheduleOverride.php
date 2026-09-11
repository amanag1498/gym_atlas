<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutScheduleOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'workout_plan_id', 'workout_plan_day_id', 'created_by_user_id',
        'gym_id', 'branch_id', 'independent_trainer_member_relationship_id',
        'original_date', 'replacement_date', 'override_type', 'status', 'reason', 'timezone',
    ];

    protected function casts(): array
    {
        return ['original_date' => 'date:Y-m-d', 'replacement_date' => 'date:Y-m-d'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    public function planDay(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlanDay::class, 'workout_plan_day_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
