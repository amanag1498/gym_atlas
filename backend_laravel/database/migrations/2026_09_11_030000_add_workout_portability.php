<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('source_shared_workout_plan_id')->nullable()
                ->after('source_workout_book_id')->constrained('workout_plans')->nullOnDelete();
            $table->foreignId('source_shared_by_user_id')->nullable()
                ->after('source_shared_workout_plan_id')->constrained('users')->nullOnDelete();
            $table->timestamp('shared_adopted_at')->nullable()->after('source_shared_by_user_id');
        });

        Schema::create('workout_plan_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('recipient_email')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['recipient_user_id', 'status'], 'workout_plan_shares_recipient_status_idx');
        });

        Schema::create('workout_history_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_format', 40);
            $table->string('source_filename')->nullable();
            $table->string('source_file_hash', 64);
            $table->string('status', 20)->default('previewed');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->json('summary');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'source_format', 'source_file_hash'], 'workout_history_import_unique_source');
        });

        Schema::create('workout_history_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_history_import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('row_hash', 64);
            $table->string('status', 24);
            $table->foreignId('matched_exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->foreignId('workout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->json('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->unique(['workout_history_import_batch_id', 'row_hash'], 'workout_history_import_row_unique');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('source_import_batch_id')->nullable()
                ->after('workout_schedule_override_id')->constrained('workout_history_import_batches')->nullOnDelete();
            $table->string('source_external_id', 64)->nullable()->after('source_import_batch_id');
            $table->unique(['member_id', 'source_import_batch_id', 'source_external_id'], 'workout_sessions_import_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropUnique('workout_sessions_import_source_unique');
            $table->dropConstrainedForeignId('source_import_batch_id');
            $table->dropColumn('source_external_id');
        });
        Schema::dropIfExists('workout_history_import_rows');
        Schema::dropIfExists('workout_history_import_batches');
        Schema::dropIfExists('workout_plan_shares');
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_shared_workout_plan_id');
            $table->dropConstrainedForeignId('source_shared_by_user_id');
            $table->dropColumn('shared_adopted_at');
        });
    }
};
