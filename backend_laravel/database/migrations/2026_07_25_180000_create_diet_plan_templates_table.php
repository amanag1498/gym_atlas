<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diet_plan_templates', function (Blueprint $table): void {
            $table->id();
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
            $table->json('meals');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['status', 'goal']);
        });
    }

    public function down(): void { Schema::dropIfExists('diet_plan_templates'); }
};
