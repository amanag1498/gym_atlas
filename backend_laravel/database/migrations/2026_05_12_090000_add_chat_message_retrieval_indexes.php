<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->index(['room', 'id'], 'chat_messages_room_id_idx');
            $table->index('created_at', 'chat_messages_created_at_idx');
            $table->unique(['room', 'sender_id', 'client_message_id'], 'chat_messages_room_sender_client_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropUnique('chat_messages_room_sender_client_unique');
            $table->dropIndex('chat_messages_created_at_idx');
            $table->dropIndex('chat_messages_room_id_idx');
        });
    }
};
