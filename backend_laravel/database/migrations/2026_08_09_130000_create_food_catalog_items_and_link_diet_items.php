<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('default_quantity')->nullable();
            $table->decimal('serving_size_g', 8, 2)->nullable();
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('protein_g', 8, 2)->nullable();
            $table->decimal('carbs_g', 8, 2)->nullable();
            $table->decimal('fats_g', 8, 2)->nullable();
            $table->decimal('fiber_g', 8, 2)->nullable();
            $table->json('dietary_tags')->nullable();
            $table->json('allergens')->nullable();
            $table->text('notes')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'category', 'name']);
        });

        Schema::table('diet_plan_meal_items', function (Blueprint $table): void {
            $table->foreignId('food_catalog_item_id')
                ->nullable()
                ->after('diet_plan_meal_id')
                ->constrained('food_catalog_items')
                ->nullOnDelete();
            $table->decimal('fiber_g', 8, 2)->nullable()->after('fats_g');
        });
    }

    public function down(): void
    {
        Schema::table('diet_plan_meal_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('food_catalog_item_id');
            $table->dropColumn('fiber_g');
        });

        Schema::dropIfExists('food_catalog_items');
    }
};
