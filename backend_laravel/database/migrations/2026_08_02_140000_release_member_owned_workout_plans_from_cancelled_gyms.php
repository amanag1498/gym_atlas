<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('workout_plans as workout_plan')
            ->join('member_profiles as member_profile', function ($join): void {
                $join->on('member_profile.user_id', '=', 'workout_plan.member_id')
                    ->on('member_profile.gym_id', '=', 'workout_plan.gym_id');
            })
            ->whereColumn('workout_plan.created_by_user_id', 'workout_plan.member_id')
            ->whereNull('workout_plan.trainer_id')
            ->whereNull('workout_plan.independent_trainer_member_relationship_id')
            ->where('workout_plan.is_member_editable', true)
            ->where('member_profile.is_active', false)
            ->where('member_profile.membership_status', 'cancelled')
            ->select('workout_plan.id')
            ->orderBy('workout_plan.id')
            ->chunkById(500, function ($plans): void {
                $planIds = $plans->pluck('id');
                DB::table('workout_sessions')
                    ->whereIn('workout_plan_id', $planIds)
                    ->update([
                        'gym_id' => null,
                        'branch_id' => null,
                    ]);
                DB::table('workout_plans')
                    ->whereIn('id', $planIds)
                    ->update([
                        'gym_id' => null,
                        'branch_id' => null,
                        'status' => 'active',
                    ]);
            }, 'workout_plan.id', 'id');
    }

    public function down(): void
    {
        // This data repair intentionally cannot infer a former gym after release.
    }
};
