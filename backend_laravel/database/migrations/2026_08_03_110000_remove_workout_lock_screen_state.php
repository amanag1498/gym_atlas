<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workout_session_actions');

        if (Schema::hasColumn('workout_sets', 'entry_source')) {
            Schema::table('workout_sets', function (Blueprint $table): void {
                $table->dropColumn('entry_source');
            });
        }

        $sessionColumns = array_values(array_filter([
            'current_workout_session_exercise_id',
            'current_set_number',
            'rest_ends_at',
            'state_revision',
        ], fn (string $column): bool => Schema::hasColumn('workout_sessions', $column)));

        if ($sessionColumns !== []) {
            Schema::table('workout_sessions', function (Blueprint $table) use ($sessionColumns): void {
                $table->dropColumn($sessionColumns);
            });
        }
    }

    public function down(): void
    {
        // The removed lock-screen workout subsystem is intentionally not recreated.
    }
};
