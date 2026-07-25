<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_email');
            $table->string('category');
            $table->string('subject');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'category', 'created_at']);
            $table->index(['recipient_email', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('email_deliveries'); }
};
