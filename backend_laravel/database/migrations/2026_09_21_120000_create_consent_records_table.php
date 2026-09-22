<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 80);
            $table->string('policy_version', 40);
            $table->string('source', 40)->default('app');
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notice_snapshot')->nullable();
            $table->string('notice_hash', 64)->nullable();
            $table->string('notice_url', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
            $table->index(['user_id', 'purpose', 'created_at']);
            $table->index(['purpose', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
