<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_business_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('waba_id')->unique();
            $table->string('business_name')->nullable();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 30)->default('connected');
            $table->string('health_status', 30)->default('unknown');
            $table->text('last_error')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });

        Schema::create('whatsapp_phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_business_account_id', 'wa_phone_account_fk')
                ->constrained('whatsapp_business_accounts')->cascadeOnDelete();
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->string('quality_rating', 30)->nullable();
            $table->string('code_verification_status', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_business_account_id', 'wa_template_account_fk')
                ->constrained('whatsapp_business_accounts')->cascadeOnDelete();
            $table->string('provider_template_id')->nullable();
            $table->string('name');
            $table->string('language', 20);
            $table->string('category', 30)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('quality_rating', 30)->nullable();
            $table->json('components')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['whatsapp_business_account_id', 'name', 'language'],
                'wa_templates_account_name_language_unique'
            );
            $table->index(['status', 'category']);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_business_account_id', 'wa_conversation_account_fk')
                ->constrained('whatsapp_business_accounts')->cascadeOnDelete();
            $table->foreignId('whatsapp_phone_number_id', 'wa_conversation_phone_fk')
                ->constrained('whatsapp_phone_numbers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_wa_id', 30);
            $table->string('contact_name')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('service_window_expires_at')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['whatsapp_phone_number_id', 'contact_wa_id'],
                'wa_conversations_phone_contact_unique'
            );
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('whatsapp_conversation_id', 'wa_message_conversation_fk')
                ->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction', 20);
            $table->string('message_type', 30)->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('received');
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('payload_sha256', 64)->unique();
            $table->string('object_type')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('payload');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_phone_numbers');
        Schema::dropIfExists('whatsapp_business_accounts');
    }
};
