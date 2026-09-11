<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workout_templates', 'workout_plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('progression_policy', 40)->default('off');
                $table->json('progression_config')->nullable();
                $table->unsignedSmallInteger('progression_version')->default(1);
            });
        }

        foreach (['workout_template_exercises', 'workout_plan_exercises'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('group_key', 40)->nullable();
                $table->string('group_type', 20)->nullable();
                $table->unsignedSmallInteger('group_order')->nullable();
                $table->unsignedSmallInteger('group_rounds')->nullable();
                $table->unsignedInteger('transition_seconds')->nullable();
                $table->string('rest_after', 20)->default('exercise');
                $table->string('progression_policy', 40)->default('off');
                $table->json('progression_config')->nullable();
                $table->unsignedSmallInteger('progression_version')->default(1);
                $table->index(['group_key', 'group_order']);
            });
        }

        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->string('group_key', 40)->nullable();
            $table->string('group_type', 20)->nullable();
            $table->unsignedSmallInteger('group_order')->nullable();
            $table->unsignedSmallInteger('group_rounds')->nullable();
            $table->unsignedInteger('transition_seconds')->nullable();
            $table->string('rest_after', 20)->default('exercise');
            $table->string('progression_policy', 40)->default('off');
            $table->json('progression_config')->nullable();
            $table->unsignedSmallInteger('progression_version')->default(1);
            $table->index(['workout_session_id', 'group_key', 'group_order'], 'session_exercise_group_idx');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->decimal('best_estimated_one_rep_max', 10, 2)->nullable();
            $table->decimal('estimated_one_rep_max_weight', 10, 2)->nullable();
            $table->unsignedInteger('estimated_one_rep_max_reps')->nullable();
            $table->string('estimated_one_rep_max_formula', 40)->nullable();
            $table->timestamp('estimated_one_rep_max_achieved_at')->nullable();
        });

        Schema::create('workout_progression_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_plan_exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_workout_session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->string('policy', 40);
            $table->unsignedSmallInteger('algorithm_version')->default(1);
            $table->string('action', 20);
            $table->string('status', 20)->default('pending');
            $table->json('current_prescription');
            $table->json('recommended_prescription');
            $table->json('decision_inputs');
            $table->text('explanation');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['workout_plan_exercise_id', 'source_workout_session_id'],
                'progression_plan_exercise_session_unique'
            );
            $table->index(['trainer_id', 'status', 'created_at'], 'progression_trainer_status_idx');
            $table->index(['member_id', 'status', 'created_at'], 'progression_member_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_progression_recommendations');

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropColumn([
                'best_estimated_one_rep_max', 'estimated_one_rep_max_weight',
                'estimated_one_rep_max_reps', 'estimated_one_rep_max_formula',
                'estimated_one_rep_max_achieved_at',
            ]);
        });

        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->dropIndex('session_exercise_group_idx');
            $table->dropColumn([
                'group_key', 'group_type', 'group_order', 'group_rounds',
                'transition_seconds', 'rest_after', 'progression_policy',
                'progression_config', 'progression_version',
            ]);
        });

        foreach (['workout_template_exercises', 'workout_plan_exercises'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['group_key', 'group_order']);
                $table->dropColumn([
                    'group_key', 'group_type', 'group_order', 'group_rounds',
                    'transition_seconds', 'rest_after', 'progression_policy',
                    'progression_config', 'progression_version',
                ]);
            });
        }

        foreach (['workout_templates', 'workout_plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['progression_policy', 'progression_config', 'progression_version']);
            });
        }
    }
};
