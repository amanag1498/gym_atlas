<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropUnique(['member_id', 'exercise_id']);
            $table->string('coaching_scope_key', 80)->default('independent:self')->after('branch_id');
        });

        DB::table('personal_records')
            ->select(['id', 'gym_id', 'branch_id', 'workout_session_id'])
            ->orderBy('id')
            ->each(function (object $record): void {
                $independentRelationshipId = $record->gym_id === null && $record->workout_session_id !== null
                    ? DB::table('workout_sessions')
                        ->join('workout_plans', 'workout_plans.id', '=', 'workout_sessions.workout_plan_id')
                        ->where('workout_sessions.id', $record->workout_session_id)
                        ->value('workout_plans.independent_trainer_member_relationship_id')
                    : null;
                DB::table('personal_records')
                    ->where('id', $record->id)
                    ->update([
                        'coaching_scope_key' => $record->gym_id === null
                            ? ($independentRelationshipId !== null
                                ? 'independent:'.(int) $independentRelationshipId
                                : 'independent:self')
                            : 'gym:'.(int) $record->gym_id.':branch:'.(int) ($record->branch_id ?? 0),
                    ]);
            });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->unique(
                ['member_id', 'exercise_id', 'coaching_scope_key'],
                'personal_records_member_exercise_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropUnique('personal_records_member_exercise_scope_unique');
            $table->dropColumn('coaching_scope_key');
        });

        // The legacy schema allowed only one record per member/exercise. Keep
        // rollback available only where the data still satisfies that contract.
        Schema::table('personal_records', function (Blueprint $table): void {
            $table->unique(['member_id', 'exercise_id']);
        });
    }
};
