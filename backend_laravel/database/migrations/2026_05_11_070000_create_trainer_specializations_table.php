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
        Schema::create('trainer_specializations', function (Blueprint $table): void {
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

        $defaults = [
            ['name' => 'Strength', 'icon' => 'fitness_center', 'description' => 'Strength training, progressive overload, and technique coaching.', 'sort_order' => 1],
            ['name' => 'Fat loss', 'icon' => 'local_fire_department', 'description' => 'Sustainable fat loss with training consistency and conditioning.', 'sort_order' => 2],
            ['name' => 'Body recomposition', 'icon' => 'monitor_weight', 'description' => 'Build muscle while improving body composition.', 'sort_order' => 3],
            ['name' => 'Mobility', 'icon' => 'self_improvement', 'description' => 'Movement quality, flexibility, mobility, and recovery work.', 'sort_order' => 4],
            ['name' => 'Sports conditioning', 'icon' => 'directions_run', 'description' => 'Performance, stamina, agility, and athletic conditioning.', 'sort_order' => 5],
        ];

        DB::table('trainer_specializations')->insert(
            collect($defaults)->map(fn (array $specialization): array => [
                'name' => $specialization['name'],
                'slug' => Str::slug($specialization['name']),
                'icon' => $specialization['icon'],
                'description' => $specialization['description'],
                'sort_order' => $specialization['sort_order'],
                'status' => 'active',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_specializations');
    }
};
