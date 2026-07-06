<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
        });

        Schema::create('saved_gyms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'gym_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['gym_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_gyms');

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
        });
    }
};
