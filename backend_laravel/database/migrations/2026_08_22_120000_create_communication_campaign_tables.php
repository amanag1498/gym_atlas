<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('status', 30)->default('revoked');
            $table->string('phone_e164', 20);
            $table->string('source', 50);
            $table->string('wording_version', 50);
            $table->json('evidence')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'gym_id', 'purpose'], 'wa_consents_user_gym_purpose_unique');
            $table->index(['gym_id', 'purpose', 'status']);
        });

        Schema::create('communication_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('audience_type', 40);
            $table->json('audience_filters')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['gym_id', 'status', 'scheduled_for']);
        });

        Schema::create('communication_campaign_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_campaign_id', 'campaign_channel_campaign_fk')
                ->constrained('communication_campaigns')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('notification_type')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('template_parameters')->nullable();
            $table->timestamps();

            $table->unique(['communication_campaign_id', 'channel'], 'campaign_channels_unique');
        });

        Schema::create('communication_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_campaign_id', 'campaign_recipient_campaign_fk')
                ->constrained('communication_campaigns')->cascadeOnDelete();
            $table->foreignId('communication_campaign_channel_id', 'campaign_recipient_channel_fk')
                ->constrained('communication_campaign_channels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30);
            $table->string('destination')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('exclusion_reason')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('recipient_snapshot')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['communication_campaign_id', 'communication_campaign_channel_id', 'user_id'],
                'campaign_recipients_user_channel_unique'
            );
            $table->index(['communication_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_recipients');
        Schema::dropIfExists('communication_campaign_channels');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('whatsapp_consents');
    }
};
