<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 40)->nullable();
            $table->string('app_role', 40)->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'app_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
