<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('goal')->nullable();
            $table->unsignedInteger('daily_calorie_target')->nullable();
            $table->decimal('protein_target_g', 8, 2)->nullable();
            $table->decimal('carbs_target_g', 8, 2)->nullable();
            $table->decimal('fats_target_g', 8, 2)->nullable();
            $table->text('dietary_preferences')->nullable();
            $table->text('allergies_and_restrictions')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'status', 'starts_on']);
            $table->index(['gym_id', 'branch_id', 'status']);
        });

        Schema::create('diet_plan_meals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diet_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('meal_type')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('protein_g', 8, 2)->nullable();
            $table->decimal('carbs_g', 8, 2)->nullable();
            $table->decimal('fats_g', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('diet_plan_meal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diet_plan_meal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('quantity')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('protein_g', 8, 2)->nullable();
            $table->decimal('carbs_g', 8, 2)->nullable();
            $table->decimal('fats_g', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('diet_meal_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diet_plan_meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->date('logged_for');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['diet_plan_meal_id', 'member_id', 'logged_for'], 'diet_meal_logs_unique_daily_meal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diet_meal_logs');
        Schema::dropIfExists('diet_plan_meal_items');
        Schema::dropIfExists('diet_plan_meals');
        Schema::dropIfExists('diet_plans');
    }
};
