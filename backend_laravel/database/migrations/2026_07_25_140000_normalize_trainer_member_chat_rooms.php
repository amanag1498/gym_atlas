<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('chat_messages')
                ->where('room', 'like', 'chat:trainer:%')
                ->orderBy('id')
                ->each(function (object $message): void {
                    $canonicalRoom = preg_replace('/^chat:/', '', (string) $message->room);

                    if ($message->client_message_id !== null) {
                        $duplicateExists = DB::table('chat_messages')
                            ->where('room', $canonicalRoom)
                            ->where('sender_id', $message->sender_id)
                            ->where('client_message_id', $message->client_message_id)
                            ->where('id', '!=', $message->id)
                            ->exists();

                        if ($duplicateExists) {
                            DB::table('chat_messages')->where('id', $message->id)->delete();

                            return;
                        }
                    }

                    DB::table('chat_messages')
                        ->where('id', $message->id)
                        ->update(['room' => $canonicalRoom]);
                });

            $pairs = DB::table('chat_conversations')
                ->select(['trainer_id', 'member_id'])
                ->union(DB::table('chat_messages')->select(['trainer_id', 'member_id']))
                ->distinct()
                ->get();

            foreach ($pairs as $pair) {
                $trainerId = (int) $pair->trainer_id;
                $memberId = (int) $pair->member_id;
                $room = "trainer:{$trainerId}:member:{$memberId}";
                $trainerReadAt = DB::table('chat_conversations')
                    ->where('trainer_id', $trainerId)
                    ->where('member_id', $memberId)
                    ->max('trainer_read_at');
                $memberReadAt = DB::table('chat_conversations')
                    ->where('trainer_id', $trainerId)
                    ->where('member_id', $memberId)
                    ->max('member_read_at');

                DB::table('chat_conversations')
                    ->where('trainer_id', $trainerId)
                    ->where('member_id', $memberId)
                    ->delete();

                $latest = DB::table('chat_messages')
                    ->where('trainer_id', $trainerId)
                    ->where('member_id', $memberId)
                    ->orderByDesc('id')
                    ->first();

                DB::table('chat_conversations')->insert([
                    'room' => $room,
                    'trainer_id' => $trainerId,
                    'member_id' => $memberId,
                    'last_message_id' => $latest?->id,
                    'last_message_body' => $latest?->body,
                    'last_sender_id' => $latest?->sender_id,
                    'last_message_at' => $latest?->created_at,
                    'trainer_unread_count' => DB::table('chat_messages')
                        ->where('room', $room)
                        ->where('recipient_id', $trainerId)
                        ->whereNull('read_at')
                        ->count(),
                    'member_unread_count' => DB::table('chat_messages')
                        ->where('room', $room)
                        ->where('recipient_id', $memberId)
                        ->whereNull('read_at')
                        ->count(),
                    'trainer_read_at' => $trainerReadAt,
                    'member_read_at' => $memberReadAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Canonical rooms are intentionally retained.
    }
};
