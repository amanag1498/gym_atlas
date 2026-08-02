<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->timestamp('verification_submitted_at')->nullable()->after('verification_status');
            $table->index(['verification_status', 'verification_submitted_at'], 'trainer_verification_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->dropIndex('trainer_verification_queue_idx');
            $table->dropColumn('verification_submitted_at');
        });
    }
};
