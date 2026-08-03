<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_workout_session_exercise_id')->nullable()->after('total_volume');
            $table->unsignedInteger('current_set_number')->default(1)->after('current_workout_session_exercise_id');
            $table->timestamp('rest_ends_at')->nullable()->after('current_set_number');
            $table->unsignedBigInteger('state_revision')->default(0)->after('rest_ends_at');
        });

        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->string('entry_source', 32)->default('legacy')->after('is_completed');
        });

        Schema::create('workout_session_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('action', 32);
            $table->unsignedBigInteger('resulting_revision');
            $table->timestamps();

            $table->unique(['workout_session_id', 'idempotency_key'], 'workout_session_actions_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_session_actions');

        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->dropColumn('entry_source');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'current_workout_session_exercise_id',
                'current_set_number',
                'rest_ends_at',
                'state_revision',
            ]);
        });
    }
};
