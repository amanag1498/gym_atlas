<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workout_template_exercises', 'workout_plan_exercises'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('tracking_mode')->default('reps')->after('sets');
                $table->unsignedInteger('planned_duration_seconds')->nullable()->after('reps');
                $table->decimal('planned_distance_meters', 12, 2)->nullable()->after('planned_duration_seconds');
                $table->decimal('planned_speed_kph', 8, 2)->nullable()->after('planned_distance_meters');
                $table->unsignedInteger('planned_pace_seconds_per_km')->nullable()->after('planned_speed_kph');
                $table->decimal('target_resistance', 8, 2)->nullable()->after('target_weight');
                $table->decimal('target_machine_level', 8, 2)->nullable()->after('target_resistance');
                $table->boolean('is_per_side')->default(false)->after('target_machine_level');
                $table->boolean('is_bodyweight')->default(false)->after('is_per_side');
                $table->index(['tracking_mode', 'exercise_id'], $tableName.'_mode_exercise_idx');
            });
        }

        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->string('tracking_mode')->default('reps')->after('exercise_id');
            $table->unsignedInteger('planned_duration_seconds')->nullable()->after('planned_reps');
            $table->decimal('planned_distance_meters', 12, 2)->nullable()->after('planned_duration_seconds');
            $table->decimal('planned_speed_kph', 8, 2)->nullable()->after('planned_distance_meters');
            $table->unsignedInteger('planned_pace_seconds_per_km')->nullable()->after('planned_speed_kph');
            $table->decimal('target_resistance', 8, 2)->nullable()->after('target_weight');
            $table->decimal('target_machine_level', 8, 2)->nullable()->after('target_resistance');
            $table->boolean('is_per_side')->default(false)->after('target_machine_level');
            $table->boolean('is_bodyweight')->default(false)->after('is_per_side');
            $table->string('performed_status')->default('planned')->after('is_bodyweight');
            $table->foreignId('substituted_for_session_exercise_id')->nullable()
                ->after('performed_status')->constrained('workout_session_exercises')->nullOnDelete();
            $table->index(['tracking_mode', 'performed_status'], 'session_exercise_mode_status_idx');
        });

        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->unsignedInteger('duration_seconds')->nullable()->after('reps');
            $table->decimal('distance_meters', 12, 2)->nullable()->after('duration_seconds');
            $table->decimal('speed_kph', 8, 2)->nullable()->after('distance_meters');
            $table->unsignedInteger('pace_seconds_per_km')->nullable()->after('speed_kph');
            $table->string('effort_scale')->nullable()->after('rest_seconds');
            $table->decimal('effort_value', 4, 1)->nullable()->after('effort_scale');
            $table->string('side')->nullable()->after('effort_value');
            $table->timestamp('completed_at')->nullable()->after('is_completed');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->decimal('pre_workout_weight_kg', 8, 2)->nullable()->after('notes');
            $table->json('completion_summary')->nullable()->after('total_volume');
            $table->json('runtime_state')->nullable()->after('completion_summary');
            $table->timestamp('last_activity_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropColumn(['pre_workout_weight_kg', 'completion_summary', 'runtime_state', 'last_activity_at']);
        });

        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->dropColumn([
                'duration_seconds', 'distance_meters', 'speed_kph', 'pace_seconds_per_km',
                'effort_scale', 'effort_value', 'side', 'completed_at',
            ]);
        });

        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->dropForeign(['substituted_for_session_exercise_id']);
            $table->dropIndex('session_exercise_mode_status_idx');
            $table->dropColumn([
                'tracking_mode', 'planned_duration_seconds', 'planned_distance_meters',
                'planned_speed_kph', 'planned_pace_seconds_per_km', 'target_resistance',
                'target_machine_level', 'is_per_side', 'is_bodyweight', 'performed_status',
                'substituted_for_session_exercise_id',
            ]);
        });

        foreach (['workout_template_exercises', 'workout_plan_exercises'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_mode_exercise_idx');
                $table->dropColumn([
                    'tracking_mode', 'planned_duration_seconds', 'planned_distance_meters',
                    'planned_speed_kph', 'planned_pace_seconds_per_km', 'target_resistance',
                    'target_machine_level', 'is_per_side', 'is_bodyweight',
                ]);
            });
        }
    }
};
