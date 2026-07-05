<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropUnique('member_profiles_user_id_unique');
            $table->unique(['user_id', 'gym_id'], 'member_profiles_user_gym_unique');
        });

        Schema::create('member_gym_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('invited_email');
            $table->foreignId('assigned_trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['invited_user_id', 'status', 'created_at'], 'member_gym_invites_user_status_idx');
            $table->index(['gym_id', 'status', 'created_at'], 'member_gym_invites_gym_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_gym_invitations');

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropUnique('member_profiles_user_gym_unique');
            $table->unique('user_id', 'member_profiles_user_id_unique');
        });
    }
};
