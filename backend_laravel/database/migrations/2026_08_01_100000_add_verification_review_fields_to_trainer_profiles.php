<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->foreignId('verification_reviewed_by_user_id')
                ->nullable()
                ->after('verification_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verification_reviewed_at')->nullable()->after('verification_reviewed_by_user_id');
            $table->timestamp('verification_verified_at')->nullable()->after('verification_reviewed_at');
            $table->text('verification_rejection_reason')->nullable()->after('verification_verified_at');
            $table->text('verification_review_notes')->nullable()->after('verification_rejection_reason');

            $table->index(['gym_id', 'verification_status'], 'trainer_profiles_independent_verification_index');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->dropIndex('trainer_profiles_independent_verification_index');
            $table->dropConstrainedForeignId('verification_reviewed_by_user_id');
            $table->dropColumn([
                'verification_reviewed_at',
                'verification_verified_at',
                'verification_rejection_reason',
                'verification_review_notes',
            ]);
        });
    }
};
