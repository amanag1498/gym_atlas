<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventReminder;
use App\Models\Gym;
use App\Models\TrainerProfile;
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
            ->assertJsonPath('data.status', 'reserved')
            ->assertJsonPath('data.currency_snapshot', 'INR');

        $this->getJson('/api/member/events/bookings?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'reserved')
            ->assertJsonPath('data.0.event.id', $event->id);

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

    public function test_increasing_capacity_promotes_existing_waitlisted_members(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $first = $this->user(RoleName::Member);
        $second = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 1);

        Sanctum::actingAs($first);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated();
        Sanctum::actingAs($second);
        $this->postJson("/api/member/events/{$event->id}/book")
            ->assertCreated()
            ->assertJsonPath('data.status', 'waitlisted');

        app(EventService::class)->save($admin, ['capacity' => 2], $event);

        $this->assertDatabaseHas('event_bookings', [
            'event_id' => $event->id,
            'user_id' => $second->id,
            'status' => 'reserved',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $second->id,
            'type' => 'event_waitlist_promoted',
        ]);
    }

    public function test_member_cannot_cancel_after_event_has_started(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);

        Sanctum::actingAs($member);
        $this->postJson("/api/member/events/{$event->id}/book")->assertCreated();
        $event->forceFill([
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ])->save();

        $this->postJson("/api/member/events/{$event->id}/cancel-booking")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');
        $this->assertDatabaseHas('event_bookings', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
        ]);
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

    public function test_gym_trainer_can_list_update_and_cancel_an_event_assigned_to_them_as_host(): void
    {
        $trainer = $this->user(RoleName::Trainer);
        $owner = $this->user(RoleName::GymOwner);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Trainer Events Gym',
            'slug' => 'trainer-events-gym',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
            'timezone' => 'Asia/Kolkata',
        ]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'status' => 'active',
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'scope' => 'gym', 'gym_id' => $gym->id, 'created_by_user_id' => $owner->id, 'host_user_id' => $trainer->id,
            'title' => 'Trainer hosted mobility class', 'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHour(), 'timezone' => 'Asia/Kolkata', 'pricing_type' => 'free',
            'currency' => 'INR', 'status' => 'draft',
        ]);

        Sanctum::actingAs($trainer);
        $this->getJson('/api/trainer/events?managed_only=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $event->id);

        $this->putJson("/api/trainer/events/{$event->id}", [
            'title' => 'Published mobility class',
            'starts_at' => now('Asia/Kolkata')->addDays(2)->setTime(16, 0)->format('Y-m-d H:i:s'),
            'ends_at' => now('Asia/Kolkata')->addDays(2)->setTime(17, 0)->format('Y-m-d H:i:s'),
            'timezone' => 'Asia/Kolkata',
            'pricing_type' => 'free',
            'status' => 'published',
        ])->assertOk()->assertJsonPath('data.status', 'published');

        $this->postJson("/api/trainer/events/{$event->id}/cancel", ['reason' => 'Studio maintenance'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_trainer_cannot_manage_another_trainers_or_a_gym_event_without_enrollment(): void
    {
        $host = $this->user(RoleName::Trainer);
        $other = $this->user(RoleName::Trainer);
        $independent = $this->user(RoleName::Trainer);
        $gym = Gym::query()->create([
            'name' => 'Trainer Event Isolation Gym',
            'slug' => 'trainer-event-isolation-gym',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        foreach ([$host, $other] as $trainer) {
            TrainerProfile::query()->create([
                'user_id' => $trainer->id,
                'gym_id' => $gym->id,
                'status' => 'active',
                'is_active' => true,
            ]);
        }
        TrainerProfile::query()->create([
            'user_id' => $independent->id,
            'gym_id' => null,
            'status' => 'active',
            'is_active' => true,
        ]);
        $event = Event::query()->create([
            'scope' => 'gym', 'gym_id' => $gym->id, 'created_by_user_id' => $host->id, 'host_user_id' => $host->id,
            'title' => 'Host only event', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(),
            'timezone' => 'Asia/Kolkata', 'pricing_type' => 'free', 'currency' => 'INR', 'status' => 'draft',
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/trainer/events/{$event->id}/cancel", ['reason' => 'Not my event'])->assertForbidden();
        $this->getJson("/api/trainer/events/{$event->id}/bookings")->assertForbidden();

        Sanctum::actingAs($independent);
        $this->getJson('/api/trainer/events?managed_only=1')->assertForbidden();
        $event->update(['host_user_id' => $independent->id]);
        $this->getJson("/api/trainer/events/{$event->id}/bookings")->assertForbidden();
    }

    public function test_global_event_host_keeps_roster_and_attendance_access(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $trainer = $this->user(RoleName::Trainer);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => null,
            'status' => 'active',
            'is_active' => true,
        ]);
        $member = $this->user(RoleName::Member);
        $event = $this->event($admin, capacity: 5);
        $event->forceFill([
            'host_user_id' => $trainer->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ])->save();
        $booking = EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'status' => 'reserved',
            'booked_at' => now(),
        ]);

        Sanctum::actingAs($trainer);
        $this->getJson("/api/trainer/events/{$event->id}/bookings")
            ->assertOk()
            ->assertJsonPath('data.0.id', $booking->id);
        $this->putJson("/api/trainer/events/{$event->id}/bookings/{$booking->id}/attendance", ['status' => 'attended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'attended');
    }

    public function test_assigning_a_host_to_an_existing_gym_event_notifies_the_trainer(): void
    {
        $owner = $this->user(RoleName::GymOwner);
        $trainer = $this->user(RoleName::Trainer);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Host Notification Gym',
            'slug' => 'host-notification-gym',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
        ]);
        TrainerProfile::query()->create([
            'user_id' => $trainer->id,
            'gym_id' => $gym->id,
            'status' => 'active',
            'is_active' => true,
        ]);
        $event = Event::query()->create([
            'scope' => 'gym', 'gym_id' => $gym->id, 'created_by_user_id' => $owner->id,
            'title' => 'Evening Zumba', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(),
            'timezone' => 'Asia/Kolkata', 'pricing_type' => 'free', 'currency' => 'INR',
            'status' => 'published', 'published_at' => now(),
        ]);

        app(EventService::class)->save($owner, ['host_user_id' => $trainer->id], $event);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $trainer->id,
            'type' => 'event_updated',
            'title' => 'You were assigned as event host',
        ]);
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
