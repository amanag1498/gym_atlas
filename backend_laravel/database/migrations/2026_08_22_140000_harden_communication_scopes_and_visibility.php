<?php

use App\Support\CommunicationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->boolean('in_app_visible')->default(true)->after('scheduled_for')->index();
        });

        Schema::table('notification_channel_preferences', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable()->after('branch_id');
        });
        DB::table('notification_channel_preferences')->orderBy('id')->eachById(function ($row): void {
            DB::table('notification_channel_preferences')->where('id', $row->id)->update([
                'scope_key' => CommunicationScope::key($row->gym_id, $row->branch_id),
            ]);
        });
        Schema::table('notification_channel_preferences', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable(false)->change();
            $table->index('user_id', 'ncp_user_fk_idx');
            $table->dropUnique('notification_channel_preferences_scope_unique');
            $table->unique(
                ['user_id', 'scope_key', 'notification_type', 'channel'],
                'notification_channel_preferences_scope_unique'
            );
        });

        Schema::table('whatsapp_consents', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable()->after('gym_id');
        });
        DB::table('whatsapp_consents')->orderBy('id')->eachById(function ($row): void {
            DB::table('whatsapp_consents')->where('id', $row->id)->update([
                'scope_key' => CommunicationScope::key($row->gym_id),
            ]);
        });
        Schema::table('whatsapp_consents', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable(false)->change();
            $table->index('user_id', 'wa_consents_user_fk_idx');
            $table->dropUnique('wa_consents_user_gym_purpose_unique');
            $table->unique(['user_id', 'scope_key', 'purpose'], 'wa_consents_user_scope_purpose_unique');
        });

        Schema::table('communication_automation_rules', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable()->after('branch_id');
        });
        DB::table('communication_automation_rules')->orderBy('id')->eachById(function ($row): void {
            DB::table('communication_automation_rules')->where('id', $row->id)->update([
                'scope_key' => CommunicationScope::key($row->gym_id, $row->branch_id),
            ]);
        });
        Schema::table('communication_automation_rules', function (Blueprint $table): void {
            $table->string('scope_key', 80)->nullable(false)->change();
            $table->index('gym_id', 'comm_rules_gym_fk_idx');
            $table->dropUnique('communication_automation_scope_unique');
            $table->unique(
                ['scope_key', 'notification_type', 'recipient_role'],
                'communication_automation_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('communication_automation_rules', function (Blueprint $table): void {
            $table->dropUnique('communication_automation_scope_unique');
            $table->dropColumn('scope_key');
            $table->unique(
                ['gym_id', 'branch_id', 'notification_type', 'recipient_role'],
                'communication_automation_scope_unique'
            );
            $table->dropIndex('comm_rules_gym_fk_idx');
        });
        Schema::table('whatsapp_consents', function (Blueprint $table): void {
            $table->dropUnique('wa_consents_user_scope_purpose_unique');
            $table->dropColumn('scope_key');
            $table->unique(['user_id', 'gym_id', 'purpose'], 'wa_consents_user_gym_purpose_unique');
            $table->dropIndex('wa_consents_user_fk_idx');
        });
        Schema::table('notification_channel_preferences', function (Blueprint $table): void {
            $table->dropUnique('notification_channel_preferences_scope_unique');
            $table->dropColumn('scope_key');
            $table->unique(
                ['user_id', 'gym_id', 'branch_id', 'notification_type', 'channel'],
                'notification_channel_preferences_scope_unique'
            );
            $table->dropIndex('ncp_user_fk_idx');
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn('in_app_visible');
        });
    }
};
