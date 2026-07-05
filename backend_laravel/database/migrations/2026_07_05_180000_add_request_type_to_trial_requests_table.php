<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->string('request_type')->default('trial')->after('member_id');
            $table->string('source')->default('public_gym_profile')->after('request_type');
            $table->index(['gym_id', 'request_type', 'status'], 'trial_requests_gym_type_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('trial_requests', function (Blueprint $table): void {
            $table->dropIndex('trial_requests_gym_type_status_index');
            $table->dropColumn(['request_type', 'source']);
        });
    }
};
