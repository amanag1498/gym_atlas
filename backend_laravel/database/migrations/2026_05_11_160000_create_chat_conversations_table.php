<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('room')->unique();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->text('last_message_body')->nullable();
            $table->foreignId('last_sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('trainer_unread_count')->default(0);
            $table->unsignedInteger('member_unread_count')->default(0);
            $table->timestamp('trainer_read_at')->nullable();
            $table->timestamp('member_read_at')->nullable();
            $table->timestamps();

            $table->unique(['trainer_id', 'member_id']);
            $table->index(['trainer_id', 'last_message_at']);
            $table->index(['member_id', 'last_message_at']);
        });

        $latestMessages = DB::table('chat_messages')
            ->select('room', DB::raw('MAX(id) as last_message_id'))
            ->groupBy('room')
            ->get();

        foreach ($latestMessages as $latestMessage) {
            $message = DB::table('chat_messages')->where('id', $latestMessage->last_message_id)->first();
            if (! $message) {
                continue;
            }

            DB::table('chat_conversations')->insert([
                'room' => $message->room,
                'trainer_id' => $message->trainer_id,
                'member_id' => $message->member_id,
                'last_message_id' => $message->id,
                'last_message_body' => $message->body,
                'last_sender_id' => $message->sender_id,
                'last_message_at' => $message->created_at,
                'trainer_unread_count' => DB::table('chat_messages')
                    ->where('room', $message->room)
                    ->where('recipient_id', $message->trainer_id)
                    ->whereNull('read_at')
                    ->count(),
                'member_unread_count' => DB::table('chat_messages')
                    ->where('room', $message->room)
                    ->where('recipient_id', $message->member_id)
                    ->whereNull('read_at')
                    ->count(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
