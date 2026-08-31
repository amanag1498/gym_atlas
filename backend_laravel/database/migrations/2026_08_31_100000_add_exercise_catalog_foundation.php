<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table): void {
            $table->string('body_part')->nullable()->after('name');
            $table->string('target_muscle')->nullable()->after('muscle_group');
            $table->string('movement_pattern')->nullable()->after('difficulty');
            $table->string('default_tracking_mode')->default('reps')->after('movement_pattern');
            $table->boolean('is_bodyweight')->default(false)->after('default_tracking_mode');
            $table->boolean('supports_external_load')->default(true)->after('is_bodyweight');
            $table->boolean('is_per_side')->default(false)->after('supports_external_load');
            $table->string('review_status')->default('approved')->after('status');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');

            $table->index(['body_part', 'is_active'], 'exercises_body_part_active_idx');
            $table->index(['equipment', 'is_active'], 'exercises_equipment_active_idx');
            $table->index(['default_tracking_mode', 'is_active'], 'exercises_tracking_active_idx');
            $table->index(['review_status', 'is_active'], 'exercises_review_active_idx');
        });

        Schema::create('exercise_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key');
            $table->text('source_url')->nullable();
            $table->string('source_commit')->nullable();
            $table->string('license_code', 100);
            $table->string('source_checksum', 64);
            $table->string('status');
            $table->json('counts')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source_key', 'created_at'], 'exercise_import_batches_source_created_idx');
        });

        Schema::create('exercise_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key');
            $table->string('source_external_id');
            $table->text('source_url')->nullable();
            $table->string('source_commit')->nullable();
            $table->string('license_code', 100);
            $table->string('content_checksum', 64);
            $table->timestamp('imported_at');
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['source_key', 'source_external_id'], 'exercise_sources_source_external_unique');
            $table->index(['exercise_id', 'source_key'], 'exercise_sources_exercise_source_idx');
        });

        Schema::create('exercise_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 35);
            $table->string('name')->nullable();
            $table->text('instructions')->nullable();
            $table->json('instruction_steps')->nullable();
            $table->string('source')->nullable();
            $table->string('review_status')->default('imported');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['exercise_id', 'locale']);
            $table->index(['locale', 'review_status'], 'exercise_translations_locale_review_idx');
        });

        Schema::create('exercise_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('normalized_alias');
            $table->string('locale', 35)->default('en');
            $table->string('source')->nullable();
            $table->string('review_status')->default('approved');
            $table->timestamps();

            $table->unique(['exercise_id', 'normalized_alias', 'locale'], 'exercise_aliases_exercise_normalized_locale_unique');
            $table->index(['normalized_alias', 'locale'], 'exercise_aliases_normalized_locale_idx');
        });

        Schema::create('exercise_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('source_type');
            $table->text('remote_url')->nullable();
            $table->string('storage_disk')->nullable();
            $table->text('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('license_code', 100);
            $table->text('attribution_text')->nullable();
            $table->text('license_evidence_reference');
            $table->string('status')->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['exercise_id', 'status', 'sort_order'], 'exercise_media_exercise_status_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_media');
        Schema::dropIfExists('exercise_aliases');
        Schema::dropIfExists('exercise_translations');
        Schema::dropIfExists('exercise_sources');
        Schema::dropIfExists('exercise_import_batches');

        Schema::table('exercises', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropIndex('exercises_body_part_active_idx');
            $table->dropIndex('exercises_equipment_active_idx');
            $table->dropIndex('exercises_tracking_active_idx');
            $table->dropIndex('exercises_review_active_idx');
            $table->dropColumn([
                'body_part',
                'target_muscle',
                'movement_pattern',
                'default_tracking_mode',
                'is_bodyweight',
                'supports_external_load',
                'is_per_side',
                'review_status',
                'reviewed_at',
            ]);
        });
    }
};
