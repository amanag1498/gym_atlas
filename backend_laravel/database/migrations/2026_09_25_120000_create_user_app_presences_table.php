<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_app_presences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app_role', 40)->index();
            $table->string('device_key', 120);
            $table->string('platform', 40)->nullable();
            $table->string('device_name')->nullable();
            $table->string('app_version', 80)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('last_push_success_at')->nullable();
            $table->timestamp('uninstall_suspected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'app_role', 'device_key'], 'user_app_presence_unique_device');
            $table->index(['user_id', 'app_role', 'last_seen_at'], 'user_app_presence_user_role_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_app_presences');
    }
};
