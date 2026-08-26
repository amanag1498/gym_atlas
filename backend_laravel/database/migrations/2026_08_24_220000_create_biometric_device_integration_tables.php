<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->uuid('uuid')->unique('bio_devices_uuid_unique');
            $table->string('name', 160);
            $table->string('vendor', 80);
            $table->string('model', 120)->nullable();
            $table->string('firmware_version', 120)->nullable();
            $table->string('connector_version', 120)->nullable();
            $table->string('serial_number', 160)->nullable();
            $table->string('adapter_key', 80);
            $table->string('connection_method', 40);
            $table->json('modalities')->nullable();
            $table->json('capabilities')->nullable();
            $table->text('configuration')->nullable();
            $table->char('secret_hash', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->integer('clock_skew_seconds')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('gym_id', 'bio_devices_gym_fk')->references('id')->on('gyms')->cascadeOnDelete();
            $table->foreign('branch_id', 'bio_devices_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'bio_devices_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['gym_id', 'serial_number'], 'bio_devices_gym_serial_unique');
            $table->index(['gym_id', 'branch_id', 'is_active'], 'bio_devices_scope_idx');
        });

        Schema::create('biometric_member_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('biometric_device_id');
            $table->unsignedBigInteger('member_profile_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('external_user_id', 120);
            $table->json('modalities')->nullable();
            $table->string('enrollment_method', 40);
            $table->string('status', 32)->default('pending_capture');
            $table->text('sync_error')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('gym_id', 'bio_links_gym_fk')->references('id')->on('gyms')->cascadeOnDelete();
            $table->foreign('branch_id', 'bio_links_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('biometric_device_id', 'bio_links_device_fk')->references('id')->on('biometric_devices')->cascadeOnDelete();
            $table->foreign('member_profile_id', 'bio_links_profile_fk')->references('id')->on('member_profiles')->cascadeOnDelete();
            $table->foreign('requested_by_user_id', 'bio_links_requester_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['biometric_device_id', 'member_profile_id'], 'bio_links_device_profile_unique');
            $table->unique(['biometric_device_id', 'external_user_id'], 'bio_links_device_external_unique');
            $table->index(['gym_id', 'branch_id', 'status'], 'bio_links_scope_status_idx');
        });

        Schema::create('biometric_device_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gym_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('biometric_device_id');
            $table->unsignedBigInteger('biometric_member_link_id')->nullable();
            $table->unsignedBigInteger('attendance_log_id')->nullable();
            $table->string('vendor_event_id', 191)->nullable();
            $table->char('payload_hash', 64);
            $table->string('external_user_id', 120);
            $table->string('event_type', 40)->default('check_in');
            $table->string('modality', 32)->nullable();
            $table->string('direction', 16)->nullable();
            $table->timestamp('occurred_at_device');
            $table->timestamp('received_at');
            $table->json('normalized_payload')->nullable();
            $table->string('status', 32)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('gym_id', 'bio_events_gym_fk')->references('id')->on('gyms')->cascadeOnDelete();
            $table->foreign('branch_id', 'bio_events_branch_fk')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('biometric_device_id', 'bio_events_device_fk')->references('id')->on('biometric_devices')->cascadeOnDelete();
            $table->foreign('biometric_member_link_id', 'bio_events_link_fk')->references('id')->on('biometric_member_links')->nullOnDelete();
            $table->foreign('attendance_log_id', 'bio_events_attendance_fk')->references('id')->on('attendance_logs')->nullOnDelete();
            $table->unique(['biometric_device_id', 'vendor_event_id'], 'bio_events_device_vendor_unique');
            $table->unique(['biometric_device_id', 'payload_hash'], 'bio_events_device_hash_unique');
            $table->index(['gym_id', 'branch_id', 'status'], 'bio_events_scope_status_idx');
        });

        Schema::create('biometric_device_commands', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('biometric_device_id');
            $table->unsignedBigInteger('biometric_member_link_id')->nullable();
            $table->string('command_type', 40);
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('biometric_device_id', 'bio_commands_device_fk')->references('id')->on('biometric_devices')->cascadeOnDelete();
            $table->foreign('biometric_member_link_id', 'bio_commands_link_fk')->references('id')->on('biometric_member_links')->cascadeOnDelete();
            $table->index(['biometric_device_id', 'status', 'available_at'], 'bio_commands_delivery_idx');
        });

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('biometric_device_id')->nullable()->after('source_device');
            $table->unsignedBigInteger('biometric_device_event_id')->nullable()->after('biometric_device_id');
            $table->timestamp('occurred_at_device')->nullable()->after('biometric_device_event_id');
            $table->timestamp('received_at')->nullable()->after('occurred_at_device');
            $table->foreign('biometric_device_id', 'attendance_bio_device_fk')->references('id')->on('biometric_devices')->nullOnDelete();
            $table->foreign('biometric_device_event_id', 'attendance_bio_event_fk')->references('id')->on('biometric_device_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropForeign('attendance_bio_event_fk');
            $table->dropForeign('attendance_bio_device_fk');
            $table->dropColumn(['biometric_device_id', 'biometric_device_event_id', 'occurred_at_device', 'received_at']);
        });

        Schema::dropIfExists('biometric_device_commands');
        Schema::dropIfExists('biometric_device_events');
        Schema::dropIfExists('biometric_member_links');
        Schema::dropIfExists('biometric_devices');
    }
};
