<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->boolean('prevent_duplicate_same_day_checkins')->default(true)->after('trial_available');
        });

        Schema::create('attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('check_in_method');
            $table->timestamp('checked_in_at');
            $table->text('notes')->nullable();
            $table->string('source_device')->nullable();
            $table->timestamps();
            $table->index(['gym_id', 'branch_id', 'checked_in_at']);
            $table->index(['member_id', 'checked_in_at']);
        });

        Schema::create('attendance_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('requested_check_in_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('audience_type');
            $table->string('title');
            $table->text('message');
            $table->boolean('is_platform_wide')->default(false);
            $table->timestamp('send_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->nullOnDelete();
            $table->foreignId('member_membership_id')->nullable()->constrained('member_memberships')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('announcement_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('notification_type');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'gym_id', 'branch_id', 'notification_type'], 'notification_preferences_scope_unique');
        });

        Schema::create('scheduled_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('member_membership_id')->nullable()->constrained('member_memberships')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['type', 'status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reminders');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('announcement_recipients');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('attendance_logs');

        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropColumn('prevent_duplicate_same_day_checkins');
        });
    }
};
