<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('accepted_chat_terms_at')->nullable()->after('remember_token');
        });

        Schema::create('chat_safety_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('type', 20);
            $table->string('reason', 80)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'target_id', 'type']);
            $table->index(['target_id', 'type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_safety_actions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('accepted_chat_terms_at');
        });
    }
};
