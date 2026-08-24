<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->uuid('public_token')->nullable()->unique('events_public_token_unique')->after('id');
            $table->string('booking_audience', 24)->default('gym_members')->after('scope');
            $table->string('app_visibility', 24)->default('hosting_gym')->after('booking_audience');
            $table->boolean('public_booking_enabled')->default(false)->after('app_visibility');
            $table->json('registration_form_schema')->nullable()->after('public_booking_enabled');
            $table->timestamp('public_link_rotated_at')->nullable()->after('registration_form_schema');
        });

        DB::table('events')->orderBy('id')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                DB::table('events')->where('id', $event->id)->update([
                    'public_token' => (string) Str::uuid(),
                    'booking_audience' => $event->scope === 'global' ? 'atlas_members' : 'gym_members',
                    'app_visibility' => $event->scope === 'global' ? 'all_atlas' : 'hosting_gym',
                ]);
            }
        });

        Schema::table('event_bookings', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id', 'event_bookings_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->string('attendee_name')->nullable()->after('user_id');
            $table->string('attendee_email')->nullable()->after('attendee_name');
            $table->string('attendee_phone', 32)->nullable()->after('attendee_email');
            $table->char('attendee_key', 64)->nullable()->after('attendee_phone');
            $table->string('booking_source', 24)->default('member_app')->after('attendee_key');
            $table->char('manage_token_hash', 64)->nullable()->after('booking_source');
            $table->text('manage_token_ciphertext')->nullable()->after('manage_token_hash');
            $table->foreignId('claimed_by_user_id')->nullable()->after('manage_token_ciphertext');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');
            $table->json('registration_answers')->nullable()->after('claimed_at');

            $table->foreign('claimed_by_user_id', 'event_booking_claimed_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['event_id', 'attendee_key'], 'event_booking_attendee_unique');
            $table->index(['event_id', 'booking_source'], 'event_booking_source_idx');
        });

        Schema::table('event_reminders', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id', 'event_reminders_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_reminders', function (Blueprint $table): void {
            $table->dropForeign('event_reminders_user_fk');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('event_bookings', function (Blueprint $table): void {
            $table->dropUnique('event_booking_attendee_unique');
            $table->dropIndex('event_booking_source_idx');
            $table->dropForeign('event_booking_claimed_user_fk');
            $table->dropForeign('event_bookings_user_fk');
            $table->dropColumn([
                'attendee_name', 'attendee_email', 'attendee_phone', 'attendee_key', 'booking_source',
                'manage_token_hash', 'manage_token_ciphertext', 'claimed_by_user_id', 'claimed_at', 'registration_answers',
            ]);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique('events_public_token_unique');
            $table->dropColumn([
                'public_token', 'booking_audience', 'app_visibility', 'public_booking_enabled',
                'registration_form_schema', 'public_link_rotated_at',
            ]);
        });
    }
};
