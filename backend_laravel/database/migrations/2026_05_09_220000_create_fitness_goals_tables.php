<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fitness_goals', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 160)->unique();
            $table->string('icon', 120)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fitness_goal_member_profile', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fitness_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fitness_goal_id', 'member_profile_id'], 'fitness_goal_member_profile_unique');
        });

        $defaults = [
            ['name' => 'Lose Fat', 'icon' => 'local_fire_department', 'description' => 'Reduce body fat and improve conditioning.', 'sort_order' => 1],
            ['name' => 'Build Muscle', 'icon' => 'fitness_center', 'description' => 'Focus on lean muscle growth and body composition.', 'sort_order' => 2],
            ['name' => 'Get Stronger', 'icon' => 'bolt', 'description' => 'Increase strength, lifts, and overall performance.', 'sort_order' => 3],
            ['name' => 'Improve Endurance', 'icon' => 'directions_run', 'description' => 'Boost stamina, cardio capacity, and energy.', 'sort_order' => 4],
            ['name' => 'General Fitness', 'icon' => 'favorite', 'description' => 'Stay active, healthy, and consistent every week.', 'sort_order' => 5],
            ['name' => 'Mobility & Recovery', 'icon' => 'self_improvement', 'description' => 'Improve movement quality, flexibility, and recovery.', 'sort_order' => 6],
        ];

        DB::table('fitness_goals')->insert(
            collect($defaults)->map(fn (array $goal): array => [
                'name' => $goal['name'],
                'slug' => Str::slug($goal['name']),
                'icon' => $goal['icon'],
                'description' => $goal['description'],
                'sort_order' => $goal['sort_order'],
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fitness_goal_member_profile');
        Schema::dropIfExists('fitness_goals');
    }
};
