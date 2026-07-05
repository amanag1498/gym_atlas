<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('delivery_status')->default('sent')->after('metadata');
            $table->timestamp('delivered_at')->nullable()->after('delivery_status');
            $table->index(['room', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropIndex(['room', 'created_at']);
            $table->dropColumn(['delivery_status', 'delivered_at']);
        });
    }
};
