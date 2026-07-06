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
            $table->index(['gym_id', 'branch_id', 'member_id', 'status'], 'workout_sessions_scope_member_status_idx');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
            $table->index(['gym_id', 'branch_id', 'member_id', 'log_date'], 'weight_logs_scope_member_log_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
            $table->index(['gym_id', 'branch_id', 'member_id', 'measured_on'], 'body_measurements_scope_member_measured_idx');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
            $table->index(['gym_id', 'branch_id', 'member_id', 'captured_on'], 'progress_photos_scope_member_captured_idx');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained()->nullOnDelete();
            $table->index(['gym_id', 'branch_id', 'member_id'], 'personal_records_scope_member_idx');
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
            $table->index(['gym_id', 'branch_id', 'member_id'], 'personal_records_scope_member_idx');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
            $table->index(['gym_id', 'branch_id', 'member_id', 'captured_on'], 'progress_photos_scope_member_captured_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
            $table->index(['gym_id', 'branch_id', 'member_id', 'measured_on'], 'body_measurements_scope_member_measured_idx');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
            $table->index(['gym_id', 'branch_id', 'member_id', 'log_date'], 'weight_logs_scope_member_log_idx');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete()->after('id');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->after('gym_id');
            $table->index(['gym_id', 'branch_id', 'member_id', 'status'], 'workout_sessions_scope_member_status_idx');
        });
    }
};
