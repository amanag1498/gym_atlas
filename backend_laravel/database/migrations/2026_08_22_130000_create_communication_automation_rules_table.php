<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('notification_type', 100);
            $table->string('recipient_role', 30)->default('member');
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['gym_id', 'branch_id', 'notification_type', 'recipient_role'],
                'communication_automation_scope_unique'
            );
            $table->index(['gym_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_automation_rules');
    }
};
