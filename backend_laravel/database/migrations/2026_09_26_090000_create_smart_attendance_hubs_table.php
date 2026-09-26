<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_attendance_hubs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('smart_hubs_uuid_unique');
            $table->string('public_id', 32)->unique('smart_hubs_public_id_unique');
            $table->unsignedBigInteger('gym_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('name', 160);
            $table->string('platform', 32);
            $table->char('device_secret_hash', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('firmware_version', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('gym_id', 'smart_hubs_gym_fk')->references('id')->on('gyms')->cascadeOnDelete();
            $table->foreign('branch_id', 'smart_hubs_branch_fk')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('created_by_user_id', 'smart_hubs_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['gym_id', 'branch_id', 'is_active'], 'smart_hubs_scope_idx');
            $table->index(['status', 'last_seen_at'], 'smart_hubs_status_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_attendance_hubs');
    }
};
