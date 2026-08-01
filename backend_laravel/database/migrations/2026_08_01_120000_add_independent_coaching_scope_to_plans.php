<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('independent_trainer_member_relationship_id')
                ->nullable()
                ->after('trainer_id')
                ->constrained('independent_trainer_member_relationships')
                ->nullOnDelete();
        });

        Schema::table('diet_plans', function (Blueprint $table): void {
            $table->dropForeign(['gym_id']);
        });

        Schema::table('diet_plans', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->change();
            $table->foreignId('independent_trainer_member_relationship_id')
                ->nullable()
                ->after('trainer_id')
                ->constrained('independent_trainer_member_relationships')
                ->nullOnDelete();
        });

        Schema::table('diet_plans', function (Blueprint $table): void {
            $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('independent_trainer_member_relationship_id');
        });

        Schema::table('diet_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('independent_trainer_member_relationship_id');
            $table->dropForeign(['gym_id']);
        });

        // These rows are owned by this feature and cannot satisfy the original
        // non-null gym constraint after rollback. Their child rows cascade.
        DB::table('diet_plans')->whereNull('gym_id')->delete();

        Schema::table('diet_plans', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable(false)->change();
            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
        });
    }
};
