<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_self_enrollment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('token')->unique();
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'branch_id'], 'gym_self_enrollment_scope_unique');
            $table->index(['gym_id', 'is_active']);
        });

        Schema::create('gym_self_enrollment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_self_enrollment_link_id', 'gym_enroll_submission_link_fk')
                ->constrained('gym_self_enrollment_links')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_name', 160)->nullable();
            $table->string('submitted_email')->nullable();
            $table->string('submitted_phone', 40)->nullable();
            $table->string('outcome', 40);
            $table->string('source', 30)->default('web');
            $table->json('payload')->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_version', 30)->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'created_at']);
            $table->index(['user_id', 'gym_id']);
            $table->index(['submitted_email', 'gym_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_self_enrollment_submissions');
        Schema::dropIfExists('gym_self_enrollment_links');
    }
};
