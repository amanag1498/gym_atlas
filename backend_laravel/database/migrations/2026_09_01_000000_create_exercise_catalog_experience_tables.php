<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_favorite_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        Schema::create('member_equipment_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('preset_key')->default('custom');
            $table->json('equipment')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('exercise_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('substitute_exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('requires_trainer_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exercise_id', 'substitute_exercise_id']);
            $table->index(['exercise_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_substitutions');
        Schema::dropIfExists('member_equipment_profiles');
        Schema::dropIfExists('member_favorite_exercises');
    }
};
