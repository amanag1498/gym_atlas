<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->string('channel', 30);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['user_id', 'gym_id', 'branch_id', 'notification_type', 'channel'],
                'notification_channel_preferences_scope_unique'
            );
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30);
            $table->string('transport', 30);
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'transport'], 'notification_deliveries_transport_unique');
            $table->index(['channel', 'status', 'created_at'], 'notification_deliveries_channel_status_idx');
            $table->index(['gym_id', 'branch_id', 'status'], 'notification_deliveries_scope_status_idx');
        });

        Schema::create('communication_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('idempotency_key')->unique();
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at', 'id'], 'communication_outbox_dispatch_idx');
            $table->index(['aggregate_type', 'aggregate_id'], 'communication_outbox_aggregate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_outbox');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_channel_preferences');
    }
};
