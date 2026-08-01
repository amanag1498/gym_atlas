<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('independent_trainer_member_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('invited_email');
            $table->boolean('is_current')->nullable()->default(true);
            $table->string('status')->default('pending');
            $table->json('sharing_permissions')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['trainer_user_id', 'invited_email', 'is_current'], 'independent_trainer_member_current_unique');
            $table->index(['trainer_user_id', 'status'], 'independent_trainer_relationship_status_idx');
            $table->index(['member_user_id', 'status'], 'independent_member_relationship_status_idx');
        });

        Schema::create('independent_trainer_member_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('relationship_id')->constrained('independent_trainer_member_relationships')->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('invited_name');
            $table->string('invited_email');
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['invited_user_id', 'status'], 'independent_invites_user_status_idx');
            $table->index(['trainer_user_id', 'status'], 'independent_invites_trainer_status_idx');
            $table->index(['invited_email', 'status'], 'independent_invites_email_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('independent_trainer_member_invitations');
        Schema::dropIfExists('independent_trainer_member_relationships');
    }
};
