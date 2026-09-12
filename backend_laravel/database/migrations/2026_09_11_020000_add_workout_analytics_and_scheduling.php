<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_workout_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('target_weight_kg', 8, 2)->nullable();
            $table->timestamp('target_weight_updated_at')->nullable();
            $table->boolean('show_weight_goal')->default(true);
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->boolean('scheduled_workout_reminder_enabled')->default(false);
            $table->unsignedSmallInteger('reminder_minutes_before')->default(60);
            $table->time('default_workout_time')->default('18:00:00');
            $table->boolean('missed_workout_follow_up_enabled')->default(false);
            $table->boolean('streak_encouragement_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_schedule_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_plan_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('independent_trainer_member_relationship_id')
                ->nullable()
                ->constrained('independent_trainer_member_relationships', 'id', 'workout_override_independent_rel_fk')
                ->nullOnDelete();
            $table->date('original_date');
            $table->date('replacement_date')->nullable();
            $table->string('override_type', 20)->default('reschedule');
            $table->string('status', 20)->default('active');
            $table->text('reason')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->timestamps();

            $table->unique(
                ['workout_plan_id', 'workout_plan_day_id', 'original_date'],
                'workout_schedule_override_occurrence_unique'
            );
            $table->index(['member_id', 'replacement_date', 'status'], 'workout_schedule_override_member_date_idx');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('workout_schedule_override_id')->nullable()
                ->after('workout_plan_day_id')
                ->constrained('workout_schedule_overrides')->nullOnDelete();
        });

        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->foreignId('progression_recommendation_id')->nullable()
                ->after('progression_version')
                ->constrained('workout_progression_recommendations')->nullOnDelete();
            $table->text('progression_explanation')->nullable()->after('progression_recommendation_id');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->foreignId('estimated_one_rep_max_workout_set_id')->nullable()
                ->after('estimated_one_rep_max_formula')
                ->constrained('workout_sets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('estimated_one_rep_max_workout_set_id');
        });
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workout_schedule_override_id');
        });
        Schema::table('workout_session_exercises', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('progression_recommendation_id');
            $table->dropColumn('progression_explanation');
        });
        Schema::dropIfExists('workout_schedule_overrides');
        Schema::dropIfExists('member_workout_preferences');
    }
};
