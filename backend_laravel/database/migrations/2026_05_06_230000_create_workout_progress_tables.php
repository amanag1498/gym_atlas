<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('muscle_group');
            $table->json('secondary_muscles')->nullable();
            $table->string('equipment')->nullable();
            $table->string('difficulty')->nullable();
            $table->text('instructions')->nullable();
            $table->string('image_url')->nullable();
            $table->string('video_url')->nullable();
            $table->boolean('is_global')->default(false);
            $table->string('status')->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workout_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('goal')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('duration_weeks')->default(1);
            $table->json('weekly_schedule')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('workout_template_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('label')->nullable();
            $table->string('focus')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_template_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_template_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedInteger('sets')->default(1);
            $table->string('reps')->nullable();
            $table->decimal('target_weight', 10, 2)->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workout_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('goal')->nullable();
            $table->string('difficulty')->nullable();
            $table->unsignedInteger('duration_weeks')->default(1);
            $table->json('weekly_schedule')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_plan_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('label')->nullable();
            $table->string('focus')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_plan_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_plan_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedInteger('sets')->default(1);
            $table->string('reps')->nullable();
            $table->decimal('target_weight', 10, 2)->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workout_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('started_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('session_date');
            $table->string('status')->default('active');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_volume', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('workout_session_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_plan_exercise_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedInteger('planned_sets')->nullable();
            $table->string('planned_reps')->nullable();
            $table->decimal('target_weight', 10, 2)->nullable();
            $table->unsignedInteger('rest_timer_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->unsignedInteger('reps')->default(0);
            $table->decimal('weight', 10, 2)->default(0);
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(true);
            $table->timestamps();
        });

        Schema::create('weight_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('log_date');
            $table->decimal('weight_kg', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('body_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('measured_on');
            $table->decimal('chest_cm', 8, 2)->nullable();
            $table->decimal('waist_cm', 8, 2)->nullable();
            $table->decimal('hips_cm', 8, 2)->nullable();
            $table->decimal('arm_cm', 8, 2)->nullable();
            $table->decimal('thigh_cm', 8, 2)->nullable();
            $table->decimal('calf_cm', 8, 2)->nullable();
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('progress_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_url');
            $table->string('photo_type')->default('other');
            $table->string('album_key')->nullable();
            $table->date('captured_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->foreignId('workout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('best_weight', 10, 2)->default(0);
            $table->unsignedInteger('best_reps')->default(0);
            $table->decimal('best_volume', 12, 2)->default(0);
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
        Schema::dropIfExists('progress_photos');
        Schema::dropIfExists('body_measurements');
        Schema::dropIfExists('weight_logs');
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workout_session_exercises');
        Schema::dropIfExists('workout_sessions');
        Schema::dropIfExists('workout_plan_exercises');
        Schema::dropIfExists('workout_plan_days');
        Schema::dropIfExists('workout_plans');
        Schema::dropIfExists('workout_template_exercises');
        Schema::dropIfExists('workout_template_days');
        Schema::dropIfExists('workout_templates');
        Schema::dropIfExists('exercises');
    }
};
