<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_email_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invited_name');
            $table->string('invited_email');
            $table->string('status')->default('pending');
            $table->json('payload');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['invited_email', 'status'], 'member_email_invites_email_status_idx');
            $table->index(['gym_id', 'status'], 'member_email_invites_gym_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_email_invitations');
    }
};
