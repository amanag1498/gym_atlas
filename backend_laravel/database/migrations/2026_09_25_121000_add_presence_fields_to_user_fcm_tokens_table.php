<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_fcm_tokens', function (Blueprint $table): void {
            $table->string('device_key', 120)->nullable()->after('device_name')->index();
            $table->string('app_version', 80)->nullable()->after('device_key');
            $table->timestamp('last_push_success_at')->nullable()->after('last_seen_at');
            $table->timestamp('uninstall_suspected_at')->nullable()->after('last_push_success_at');
            $table->timestamp('revoked_at')->nullable()->after('uninstall_suspected_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_fcm_tokens', function (Blueprint $table): void {
            $table->dropColumn([
                'device_key',
                'app_version',
                'last_push_success_at',
                'uninstall_suspected_at',
                'revoked_at',
            ]);
        });
    }
};
