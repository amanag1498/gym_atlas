<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Mail\TransactionalNotificationMail;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventReminder;
use App\Models\Gym;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Events\EventService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicEventBookingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Queue::fake();
        Mail::fake();
    }

    public function test_public_event_page_and_read_api_only_expose_enabled_anyone_events(): void
    {
        $event = $this->publicEvent();

        $this->get(route('public.events.show', $event->public_token))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('No Atlas account or gym membership is required.');

        $this->getJson('/api/public/events/'.$event->public_token)
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.public_token', $event->public_token)
            ->assertJsonMissingPath('data.bookings');

        $event->update(['public_booking_enabled' => false]);
        $this->get(route('public.events.show', $event->public_token))->assertNotFound();
        $this->getJson('/api/public/events/'.$event->public_token)->assertNotFound();
        $this->post(route('public.events.book', $event->public_token), [
            'name' => 'Blocked Guest', 'email' => 'blocked@example.com', 'phone' => '9876543210',
        ])->assertNotFound();
    }

    public function test_public_event_is_hidden_when_its_gym_is_not_operational(): void
    {
        $event = $this->publicEvent();
        $event->gym()->update(['operational_access_enabled' => false]);

        $this->get(route('public.events.show', $event->public_token))->assertNotFound();
        $this->getJson('/api/public/events/'.$event->public_token)->assertNotFound();

        $member = $this->member();
        Sanctum::actingAs($member);
        $this->postJson('/api/member/events/'.$event->id.'/book')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event');
    }

    public function test_link_only_event_resolves_by_token_without_appearing_in_member_feed(): void
    {
        $event = $this->publicEvent();
        $event->update(['app_visibility' => 'link_only']);
        $member = $this->member();
        Sanctum::actingAs($member);

        $this->getJson('/api/member/events')->assertOk()->assertJsonMissing(['id' => $event->id]);
        $this->getJson('/api/member/events/public/'.$event->public_token)
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.booking', null);
        $this->getJson('/api/member/events/'.$event->id)->assertNotFound();
        $this->postJson('/api/member/events/'.$event->id.'/book')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event');
        $this->postJson('/api/member/events/public/'.$event->public_token.'/book')
            ->assertCreated()
            ->assertJsonPath('data.status', 'reserved');
    }

    public function test_guest_can_book_without_creating_an_atlas_or_gym_member_profile(): void
    {
        $event = $this->publicEvent();
        $usersBefore = User::query()->count();
        $profilesBefore = MemberProfile::query()->count();

        $response = $this->post(route('public.events.book', $event->public_token), [
            'name' => 'Guest Visitor',
            'email' => 'GUEST@example.com',
            'phone' => '+91 98765 43210',
        ])->assertRedirect();

        $response->assertSessionHas('booking_created', true);
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Your place is confirmed.')
            ->assertSee('Save in Atlas app');

        $booking = EventBooking::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertNull($booking->user_id);
        $this->assertSame('guest@example.com', $booking->attendee_email);
        $this->assertSame('919876543210', $booking->attendee_phone);
        $this->assertSame('public_web', $booking->booking_source);
        $this->assertNotNull($booking->manage_token_hash);
        $this->assertNotNull($booking->manage_token_ciphertext);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($profilesBefore, MemberProfile::query()->count());
        $this->assertDatabaseCount('event_reminders', 2);
        $this->assertDatabaseHas('event_reminders', ['event_booking_id' => $booking->id, 'user_id' => null]);
        Mail::assertSent(TransactionalNotificationMail::class);

        $this->from(route('public.events.show', $event->public_token))->post(route('public.events.book', $event->public_token), [
            'name' => 'Guest Visitor', 'email' => 'guest@example.com', 'phone' => '919876543210',
        ])->assertRedirect(route('public.events.show', $event->public_token))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_bookings', 1);
    }

    public function test_guest_can_manage_and_cancel_after_public_link_is_disabled(): void
    {
        $event = $this->publicEvent();
        $result = app(EventService::class)->bookGuest($event, [
            'name' => 'Link Holder', 'email' => 'holder@example.com', 'phone' => '9876543210',
        ]);
        $booking = $result['booking'];
        $event->update(['public_booking_enabled' => false, 'booking_audience' => 'gym_members']);

        $manageUrl = route('public.events.manage', [$event->public_token, $booking, $result['manage_token']]);
        $this->get($manageUrl)->assertOk()->assertSee('Link Holder');
        $this->post(route('public.events.cancel', [$event->public_token, $booking, $result['manage_token']]))
            ->assertRedirect($manageUrl);

        $this->assertDatabaseHas('event_bookings', ['id' => $booking->id, 'status' => 'cancelled']);
        $this->get($manageUrl)->assertOk()->assertSee('cancelled');
    }

    public function test_member_can_claim_guest_booking_without_becoming_a_gym_member(): void
    {
        $event = $this->publicEvent();
        $member = $this->member();
        $result = app(EventService::class)->bookGuest($event, [
            'name' => 'Claim Me', 'email' => $member->email, 'phone' => '9876543211',
        ]);
        $event->update(['public_booking_enabled' => false]);

        Sanctum::actingAs($member);
        $this->getJson('/api/member/events/public/'.$event->public_token)->assertNotFound();
        $this->getJson('/api/member/events/public/'.$event->public_token.'?manage_token='.$result['manage_token'])
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
        $this->postJson('/api/member/events/'.$event->id.'/claim-booking', ['manage_token' => $result['manage_token']])
            ->assertOk()
            ->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.booking_source', 'claimed_guest');

        $this->assertDatabaseHas('event_bookings', [
            'id' => $result['booking']->id, 'user_id' => $member->id, 'status' => 'reserved',
            'claimed_by_user_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('member_profiles', ['user_id' => $member->id, 'gym_id' => $event->gym_id]);
        $this->assertDatabaseHas('event_reminders', ['event_booking_id' => $result['booking']->id, 'user_id' => $member->id]);
    }

    public function test_guest_reminder_is_delivered_by_email_without_a_user_record(): void
    {
        $event = $this->publicEvent();
        $result = app(EventService::class)->bookGuest($event, [
            'name' => 'Reminder Guest', 'email' => 'reminder@example.com', 'phone' => '9876543214',
        ]);
        $reminder = EventReminder::query()->where('event_booking_id', $result['booking']->id)->where('type', '1h')->firstOrFail();
        $reminder->update(['scheduled_for' => now()->subMinute(), 'status' => 'pending']);
        EventReminder::query()->where('event_booking_id', $result['booking']->id)->whereKeyNot($reminder->id)->update(['status' => 'cancelled']);

        $this->assertSame(1, app(EventService::class)->runDueReminders());
        $this->assertSame('sent', $reminder->fresh()->status);
        Mail::assertSent(TransactionalNotificationMail::class, 2);
    }

    public function test_claim_merges_duplicate_booking_and_preserves_capacity_and_waitlist_order(): void
    {
        $event = $this->publicEvent(capacity: 2);
        $member = $this->member();

        Sanctum::actingAs($member);
        $this->postJson('/api/member/events/public/'.$event->public_token.'/book')->assertCreated()->assertJsonPath('data.status', 'reserved');

        $duplicate = app(EventService::class)->bookGuest($event, [
            'name' => 'Same Person', 'email' => 'same@example.com', 'phone' => '9876543212',
        ]);
        $waiting = app(EventService::class)->bookGuest($event, [
            'name' => 'Next Person', 'email' => 'next@example.com', 'phone' => '9876543213',
        ]);
        $this->assertSame('waitlisted', $waiting['booking']->status);

        $this->postJson('/api/member/events/'.$event->id.'/claim-booking', ['manage_token' => $duplicate['manage_token']])
            ->assertOk()
            ->assertJsonPath('data.user_id', $member->id)
            ->assertJsonPath('data.status', 'reserved');

        $this->assertDatabaseHas('event_bookings', ['id' => $duplicate['booking']->id, 'status' => 'duplicate_merged']);
        $this->assertDatabaseHas('event_bookings', ['id' => $waiting['booking']->id, 'status' => 'reserved']);
        $this->assertSame(2, EventBooking::query()->where('event_id', $event->id)->whereIn('status', ['reserved', 'attended'])->count());
        $this->assertDatabaseMissing('member_profiles', ['user_id' => $member->id, 'gym_id' => $event->gym_id]);
    }

    private function member(): User
    {
        $user = User::factory()->create(['is_active' => true, 'phone' => '9876543210']);
        $user->assignRole(RoleName::Member->value);
        $user->forceFill(['active_role' => RoleName::Member->value])->save();

        return $user;
    }

    private function publicEvent(int $capacity = 10): Event
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Public Events Gym',
            'slug' => 'public-events-gym',
            'status' => 'active',
            'is_active' => true,
            'operational_access_enabled' => true,
            'timezone' => 'Asia/Kolkata',
        ]);

        return Event::query()->create([
            'scope' => 'gym',
            'booking_audience' => 'anyone',
            'app_visibility' => 'hosting_gym',
            'public_booking_enabled' => true,
            'gym_id' => $gym->id,
            'created_by_user_id' => $owner->id,
            'title' => 'Open strength workshop',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'timezone' => 'Asia/Kolkata',
            'capacity' => $capacity,
            'waitlist_enabled' => true,
            'pricing_type' => 'free',
            'currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
            'location_name' => 'Main studio',
        ]);
    }
}
