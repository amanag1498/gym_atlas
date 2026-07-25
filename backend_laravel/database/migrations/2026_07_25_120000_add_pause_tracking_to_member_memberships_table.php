<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_memberships', function (Blueprint $table): void {
            $table->date('paused_at')->nullable()->after('status');
            $table->unsignedInteger('total_paused_days')->default(0)->after('paused_at');
            $table->date('last_resumed_at')->nullable()->after('total_paused_days');
        });
    }

    public function down(): void
    {
        Schema::table('member_memberships', function (Blueprint $table): void {
            $table->dropColumn(['paused_at', 'total_paused_days', 'last_resumed_at']);
        });
    }
};
