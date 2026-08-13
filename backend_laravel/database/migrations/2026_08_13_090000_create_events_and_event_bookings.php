<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->default('gym');
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image_url', 2048)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->dateTime('booking_opens_at')->nullable();
            $table->dateTime('booking_closes_at')->nullable();
            $table->dateTime('cancellation_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('waitlist_enabled')->default(true);
            $table->string('pricing_type', 24)->default('free');
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('payment_note', 500)->nullable();
            $table->string('location_name')->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['gym_id', 'branch_id', 'starts_at']);
            $table->index(['host_user_id', 'starts_at']);
        });

        Schema::create('event_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('reserved');
            $table->dateTime('booked_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('promoted_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->decimal('price_amount_snapshot', 10, 2)->nullable();
            $table->char('currency_snapshot', 3)->nullable();
            $table->string('payment_note_snapshot', 500)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status', 'booked_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('event_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->dateTime('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_booking_id', 'type']);
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
        Schema::dropIfExists('event_bookings');
        Schema::dropIfExists('events');
    }
};
