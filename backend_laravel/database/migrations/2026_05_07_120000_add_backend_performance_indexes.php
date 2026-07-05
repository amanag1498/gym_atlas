<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->index(['owner_user_id', 'status'], 'gyms_owner_status_idx');
            $table->index(['city_id', 'is_active'], 'gyms_city_active_idx');
            $table->index(['approval_status', 'created_at'], 'gyms_approval_created_idx');
            $table->index(['public_listing_enabled', 'public_listing_approval_status'], 'gyms_listing_status_idx');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->index(['gym_id', 'status', 'is_active'], 'branches_gym_status_active_idx');
            $table->index(['city_id', 'created_at'], 'branches_city_created_idx');
        });

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'is_active'], 'trainer_profiles_scope_active_idx');
            $table->index(['verification_status', 'created_at'], 'trainer_profiles_verification_created_idx');
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'membership_status'], 'member_profiles_scope_membership_idx');
            $table->index(['assigned_trainer_user_id', 'is_active'], 'member_profiles_trainer_active_idx');
            $table->index(['membership_expires_on', 'is_active'], 'member_profiles_expiry_active_idx');
        });

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->index(['created_by_user_id', 'created_at'], 'membership_plans_creator_created_idx');
        });

        Schema::table('member_memberships', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'status'], 'member_memberships_scope_status_idx');
            $table->index(['gym_id', 'branch_id', 'due_date'], 'member_memberships_scope_due_idx');
            $table->index(['gym_id', 'branch_id', 'expiry_date'], 'member_memberships_scope_expiry_idx');
            $table->index(['approved_by_admin_id', 'created_at'], 'member_memberships_approver_created_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'status', 'paid_at'], 'payments_scope_status_paid_idx');
            $table->index(['member_id', 'created_at'], 'payments_member_created_idx');
            $table->index(['received_by_user_id', 'created_at'], 'payments_receiver_created_idx');
        });

        Schema::table('custom_fee_audit_logs', function (Blueprint $table): void {
            $table->index(['member_id', 'changed_at'], 'custom_fee_audits_member_changed_idx');
            $table->index(['member_membership_id', 'changed_at'], 'custom_fee_audits_membership_changed_idx');
            $table->index(['changed_by', 'changed_at'], 'custom_fee_audits_actor_changed_idx');
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'status'], 'attendance_corrections_scope_status_idx');
            $table->index(['member_id', 'created_at'], 'attendance_corrections_member_created_idx');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'created_at'], 'announcements_scope_created_idx');
            $table->index(['created_by_user_id', 'created_at'], 'announcements_creator_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'type'], 'notifications_scope_type_idx');
            $table->index(['announcement_id', 'created_at'], 'notifications_announcement_created_idx');
            $table->index(['member_membership_id', 'created_at'], 'notifications_membership_created_idx');
            $table->index(['scheduled_for', 'created_at'], 'notifications_scheduled_created_idx');
        });

        Schema::table('scheduled_reminders', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'scheduled_for'], 'scheduled_reminders_user_status_scheduled_idx');
            $table->index(['gym_id', 'branch_id', 'status', 'scheduled_for'], 'scheduled_reminders_scope_status_scheduled_idx');
            $table->index(['member_membership_id', 'type'], 'scheduled_reminders_membership_type_idx');
        });

        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->index(['preferred_date', 'status'], 'trial_requests_preferred_status_idx');
            $table->index(['gym_id', 'branch_id', 'created_at'], 'trial_requests_scope_created_idx');
        });

        Schema::table('exercises', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'status'], 'exercises_scope_status_idx');
            $table->index(['is_global', 'status', 'created_at'], 'exercises_global_status_created_idx');
            $table->index(['created_by_user_id', 'created_at'], 'exercises_creator_created_idx');
        });

        Schema::table('workout_templates', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'status'], 'workout_templates_scope_status_idx');
            $table->index(['created_by_user_id', 'created_at'], 'workout_templates_creator_created_idx');
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id', 'status'], 'workout_plans_scope_member_status_idx');
            $table->index(['trainer_id', 'status', 'created_at'], 'workout_plans_trainer_status_created_idx');
            $table->index(['starts_on', 'ends_on'], 'workout_plans_starts_ends_idx');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id', 'status'], 'workout_sessions_scope_member_status_idx');
            $table->index(['session_date', 'status'], 'workout_sessions_session_status_idx');
            $table->index(['trainer_id', 'created_at'], 'workout_sessions_trainer_created_idx');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id', 'log_date'], 'weight_logs_scope_member_log_idx');
            $table->index(['logged_by_user_id', 'created_at'], 'weight_logs_logger_created_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id', 'measured_on'], 'body_measurements_scope_member_measured_idx');
            $table->index(['logged_by_user_id', 'created_at'], 'body_measurements_logger_created_idx');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id', 'captured_on'], 'progress_photos_scope_member_captured_idx');
            $table->index(['uploaded_by_user_id', 'created_at'], 'progress_photos_uploader_created_idx');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->index(['gym_id', 'branch_id', 'member_id'], 'personal_records_scope_member_idx');
            $table->index(['achieved_at', 'created_at'], 'personal_records_achieved_created_idx');
        });

        Schema::table('trainer_member_notes', function (Blueprint $table): void {
            $table->index(['member_id', 'follow_up_date'], 'trainer_member_notes_member_followup_idx');
            $table->index(['visibility', 'completed_at'], 'trainer_member_notes_visibility_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_member_notes', function (Blueprint $table): void {
            $table->dropIndex('trainer_member_notes_member_followup_idx');
            $table->dropIndex('trainer_member_notes_visibility_completed_idx');
        });

        Schema::table('personal_records', function (Blueprint $table): void {
            $table->dropIndex('personal_records_scope_member_idx');
            $table->dropIndex('personal_records_achieved_created_idx');
        });

        Schema::table('progress_photos', function (Blueprint $table): void {
            $table->dropIndex('progress_photos_scope_member_captured_idx');
            $table->dropIndex('progress_photos_uploader_created_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropIndex('body_measurements_scope_member_measured_idx');
            $table->dropIndex('body_measurements_logger_created_idx');
        });

        Schema::table('weight_logs', function (Blueprint $table): void {
            $table->dropIndex('weight_logs_scope_member_log_idx');
            $table->dropIndex('weight_logs_logger_created_idx');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropIndex('workout_sessions_scope_member_status_idx');
            $table->dropIndex('workout_sessions_session_status_idx');
            $table->dropIndex('workout_sessions_trainer_created_idx');
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropIndex('workout_plans_scope_member_status_idx');
            $table->dropIndex('workout_plans_trainer_status_created_idx');
            $table->dropIndex('workout_plans_starts_ends_idx');
        });

        Schema::table('workout_templates', function (Blueprint $table): void {
            $table->dropIndex('workout_templates_scope_status_idx');
            $table->dropIndex('workout_templates_creator_created_idx');
        });

        Schema::table('exercises', function (Blueprint $table): void {
            $table->dropIndex('exercises_scope_status_idx');
            $table->dropIndex('exercises_global_status_created_idx');
            $table->dropIndex('exercises_creator_created_idx');
        });

        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->dropIndex('trial_requests_preferred_status_idx');
            $table->dropIndex('trial_requests_scope_created_idx');
        });

        Schema::table('scheduled_reminders', function (Blueprint $table): void {
            $table->dropIndex('scheduled_reminders_user_status_scheduled_idx');
            $table->dropIndex('scheduled_reminders_scope_status_scheduled_idx');
            $table->dropIndex('scheduled_reminders_membership_type_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_scope_type_idx');
            $table->dropIndex('notifications_announcement_created_idx');
            $table->dropIndex('notifications_membership_created_idx');
            $table->dropIndex('notifications_scheduled_created_idx');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex('announcements_scope_created_idx');
            $table->dropIndex('announcements_creator_created_idx');
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table): void {
            $table->dropIndex('attendance_corrections_scope_status_idx');
            $table->dropIndex('attendance_corrections_member_created_idx');
        });

        Schema::table('custom_fee_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('custom_fee_audits_member_changed_idx');
            $table->dropIndex('custom_fee_audits_membership_changed_idx');
            $table->dropIndex('custom_fee_audits_actor_changed_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_scope_status_paid_idx');
            $table->dropIndex('payments_member_created_idx');
            $table->dropIndex('payments_receiver_created_idx');
        });

        Schema::table('member_memberships', function (Blueprint $table): void {
            $table->dropIndex('member_memberships_scope_status_idx');
            $table->dropIndex('member_memberships_scope_due_idx');
            $table->dropIndex('member_memberships_scope_expiry_idx');
            $table->dropIndex('member_memberships_approver_created_idx');
        });

        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->dropIndex('membership_plans_creator_created_idx');
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            $table->dropIndex('member_profiles_scope_membership_idx');
            $table->dropIndex('member_profiles_trainer_active_idx');
            $table->dropIndex('member_profiles_expiry_active_idx');
        });

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->dropIndex('trainer_profiles_scope_active_idx');
            $table->dropIndex('trainer_profiles_verification_created_idx');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropIndex('branches_gym_status_active_idx');
            $table->dropIndex('branches_city_created_idx');
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropIndex('gyms_owner_status_idx');
            $table->dropIndex('gyms_city_active_idx');
            $table->dropIndex('gyms_approval_created_idx');
            $table->dropIndex('gyms_listing_status_idx');
        });
    }
};
