<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('workout_plan_day_id')
                ->nullable()
                ->after('workout_plan_id')
                ->constrained('workout_plan_days')
                ->nullOnDelete();
            $table->unsignedSmallInteger('plan_day_number')->nullable()->after('workout_plan_day_id');
            $table->string('plan_day_label')->nullable()->after('plan_day_number');
            $table->string('day_selection_mode', 40)->nullable()->after('plan_day_label');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workout_plan_day_id');
            $table->dropColumn([
                'plan_day_number',
                'plan_day_label',
                'day_selection_mode',
            ]);
        });
    }
};
