<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_daily_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->date('step_date');
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('goal_steps')->default(10000);
            $table->unsignedInteger('calories_estimated')->default(0);
            $table->unsignedInteger('distance_meters')->default(0);
            $table->string('source', 30);
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'step_date'], 'member_daily_steps_user_date_unique');
            $table->index(['user_id', 'step_date'], 'member_daily_steps_user_date_idx');
            $table->index(['gym_id', 'step_date'], 'member_daily_steps_gym_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_daily_steps');
    }
};
