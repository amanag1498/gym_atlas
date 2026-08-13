<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventReminder;
use App\Models\User;
use App\Services\Events\EventService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Queue::fake();
    }

    public function test_members_can_view_and_book_global_events_without_a_gym_profile(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);

        Sanctum::actingAs($member);

        $this->getJson('/api/member/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id);

        $this->postJson("/api/member/events/{$event->id}/book")
            ->assertCreated()
            ->assertJsonPath('data.status', 'reserved');

        $this->assertDatabaseHas('event_bookings', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseCount('event_reminders', 2);
    }

    public function test_capacity_waitlist_and_cancellation_promotion_are_atomic_and_idempotent(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $first = $this->user(RoleName::Member);
        $second = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 1);

        Sanctum::actingAs($first);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated()->assertJsonPath('data.status', 'reserved');
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated()->assertJsonPath('data.status', 'reserved');
        $this->assertDatabaseCount('event_bookings', 1);

        Sanctum::actingAs($second);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated()->assertJsonPath('data.status', 'waitlisted');

        Sanctum::actingAs($first);
        $this->postJson("/api/member/events/{$event->id}/cancel-booking")->assertOk();

        $this->assertDatabaseHas('event_bookings', ['event_id' => $event->id, 'user_id' => $second->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('event_bookings', ['event_id' => $event->id, 'user_id' => $first->id, 'status' => 'cancelled']);
    }

    public function test_cancelled_event_preserves_booking_history_and_cancels_reminders(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 2);
        Sanctum::actingAs($member);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated();

        Sanctum::actingAs($admin);
        $this->postJson("/api/platform-admin/events/{$event->id}/cancel", ['reason' => 'Venue unavailable'])->assertOk();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('event_bookings', ['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'event_cancelled']);
        $this->assertDatabaseMissing('event_reminders', ['event_id' => $event->id, 'status' => 'pending']);
    }

    public function test_platform_admin_can_publish_a_pay_at_venue_event_with_timezone_conversion(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/platform-admin/events', [
            'title' => 'Strength workshop',
            'starts_at' => now('Asia/Kolkata')->addDays(5)->setTime(16, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now('Asia/Kolkata')->addDays(5)->setTime(17, 0)->format('Y-m-d H:i:s'),
            'timezone' => 'Asia/Kolkata',
            'pricing_type' => 'pay_at_venue',
            'price_amount' => 499,
            'payment_note' => 'Pay at the reception before entry.',
            'status' => 'published',
        ])->assertCreated()
            ->assertJsonPath('data.scope', 'global')
            ->assertJsonPath('data.pricing_type', 'pay_at_venue')
            ->assertJsonPath('data.price_amount', 499);

        $event = Event::query()->findOrFail($response->json('data.id'));
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertSame(10, $event->starts_at->hour);
        $this->assertNull($event->gym_id);
    }

    public function test_full_event_without_waitlist_rejects_an_extra_booking(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $first = $this->user(RoleName::Member);
        $second = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 1);
        $event->update(['waitlist_enabled' => false]);

        Sanctum::actingAs($first);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated();
        Sanctum::actingAs($second);
        $this->postJson("/api/member/events/{$event->id}/book")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event');

        $this->assertDatabaseMissing('event_bookings', ['event_id' => $event->id, 'user_id' => $second->id]);
    }

    public function test_attendance_cannot_be_recorded_too_early_but_opens_before_start(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
            'booked_at' => now(),
        ]);

        try {
            app(EventService::class)->checkIn($admin, $booking);
            $this->fail('Early attendance should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking', $exception->errors());
        }

        $this->travelTo($event->starts_at->copy()->subHour());
        $updated = app(EventService::class)->checkIn($admin, $booking->fresh());
        $this->assertSame('attended', $updated->status);
        $this->assertNotNull($updated->checked_in_at);
    }

    public function test_lifecycle_completion_preserves_history_and_creates_system_audit(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $event->forceFill(['starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()])->save();
        EventBooking::query()->create(['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'reserved', 'booked_at' => now()->subDay()]);

        app(EventService::class)->runDueReminders();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'completed']);
        $this->assertDatabaseHas('event_bookings', ['event_id' => $event->id, 'user_id' => $member->id, 'status' => 'no_show']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'system.event.completed', 'subject_id' => $event->id]);
    }

    public function test_platform_admin_can_record_attendance_for_a_global_event(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $event->forceFill(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)])->save();
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
            'booked_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/platform-admin/events/{$event->id}/bookings/{$booking->id}/attendance", ['status' => 'attended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'attended');

        $this->assertDatabaseHas('event_bookings', [
            'id' => $booking->id,
            'status' => 'attended',
            'checked_in_by_user_id' => $admin->id,
        ]);
    }

    public function test_cancelled_event_cannot_accept_attendance(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $event->forceFill([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'event_cancelled',
            'booked_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/platform-admin/events/{$event->id}/bookings/{$booking->id}/attendance", ['status' => 'attended'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');
    }

    public function test_due_reminder_is_claimed_and_sent_only_once(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $event->forceFill(['starts_at' => now()->addMinutes(30), 'ends_at' => now()->addHours(2)])->save();
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
            'booked_at' => now(),
        ]);
        $reminder = EventReminder::query()->create([
            'event_id' => $event->id,
            'event_booking_id' => $booking->id,
            'user_id' => $member->id,
            'type' => '1h',
            'scheduled_for' => now()->subMinute(),
            'status' => 'pending',
        ]);

        $this->assertSame(1, app(EventService::class)->runDueReminders());
        $this->assertSame(0, app(EventService::class)->runDueReminders());
        $this->assertSame('sent', $reminder->fresh()->status);
        $this->assertDatabaseCount('notifications', 1);
    }

    private function user(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role->value);
        $user->forceFill(['active_role' => $role->value])->save();

        return $user;
    }

    private function event(User $admin, int $capacity): Event
    {
        return Event::query()->create([
            'scope' => 'global', 'created_by_user_id' => $admin->id, 'title' => 'Community Zumba',
            'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour(), 'timezone' => 'Asia/Kolkata',
            'capacity' => $capacity, 'waitlist_enabled' => true, 'pricing_type' => 'free', 'currency' => 'INR',
            'status' => 'published', 'published_at' => now(), 'location_name' => 'Atlas Studio',
        ]);
    }
}
