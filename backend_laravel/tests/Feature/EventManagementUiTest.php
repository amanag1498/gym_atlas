<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_event_pages_use_the_shared_management_ui(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $event = $this->event($admin);

        $this->actingAs($admin)
            ->get(route('web.admin.events.index'))
            ->assertOk()
            ->assertSee('Global Event Center')
            ->assertSee('Create a new event')
            ->assertSee('Who can book?')
            ->assertDontSee('Members of the hosting gym')
            ->assertDontSee('Hosting gym member app')
            ->assertSee('Accept public-link bookings')
            ->assertSee('Event schedule')
            ->assertSee($event->title);

        $this->get(route('web.admin.events.show', $event))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Event details')
            ->assertSee('Public booking link')
            ->assertSee('Attendee and waitlist roster');

        $this->get(route('web.admin.events.edit', $event))
            ->assertOk()
            ->assertSee('Edit event')
            ->assertSee('Event identity')
            ->assertSee('Reservation settings');
    }

    public function test_gym_owner_event_pages_use_the_same_management_ui(): void
    {
        $owner = $this->user(RoleName::GymOwner);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Atlas Performance Club',
            'slug' => 'atlas-performance-club',
            'city' => 'Bengaluru',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $event = $this->event($owner, $gym);

        $this->actingAs($owner)
            ->withSession(['web_panel.gym_id' => $gym->id])
            ->get(route('web.gym.events.index', ['gym' => $gym->id]))
            ->assertOk()
            ->assertSee('Gym Event Center')
            ->assertSee('Create a new event')
            ->assertSee('Who can book?')
            ->assertSee('Accept public-link bookings')
            ->assertSee('Event schedule')
            ->assertSee($event->title);

        $this->get(route('web.gym.events.show', ['gym' => $gym->id, 'event' => $event]))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Event details')
            ->assertSee('Public booking link')
            ->assertSee('Attendee and waitlist roster');

        $this->get(route('web.gym.events.edit', ['gym' => $gym->id, 'event' => $event]))
            ->assertOk()
            ->assertSee('Edit event')
            ->assertSee('Event identity')
            ->assertSee('Reservation settings');
    }

    public function test_event_roster_renders_guest_bookings_without_a_user(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $event = $this->event($admin);
        EventBooking::query()->create([
            'event_id' => $event->id,
            'user_id' => null,
            'attendee_name' => 'Public Guest',
            'attendee_email' => 'guest@example.test',
            'attendee_phone' => '+919999999999',
            'attendee_key' => hash('sha256', 'guest@example.test'),
            'booking_source' => 'public_web',
            'status' => 'reserved',
            'booked_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('web.admin.events.show', $event))
            ->assertOk()
            ->assertSee('Public Guest')
            ->assertSee('guest@example.test')
            ->assertSee('+919999999999')
            ->assertSee('Guest');
    }

    public function test_platform_admin_can_configure_public_event_access(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);

        $this->actingAs($admin)
            ->post(route('web.admin.events.store'), [
                'title' => 'Open Community Day',
                'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addWeek()->addHour()->format('Y-m-d H:i:s'),
                'timezone' => 'Asia/Kolkata',
                'booking_audience' => 'anyone',
                'app_visibility' => 'link_only',
                'public_booking_enabled' => '1',
                'waitlist_enabled' => '1',
                'pricing_type' => 'free',
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', [
            'title' => 'Open Community Day',
            'scope' => 'global',
            'booking_audience' => 'anyone',
            'app_visibility' => 'link_only',
            'public_booking_enabled' => true,
        ]);
    }

    public function test_service_rejects_unreachable_event_visibility_combinations(): void
    {
        $admin = $this->user(RoleName::PlatformAdmin);
        $base = [
            'title' => 'Invalid event',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addWeek()->addHour()->format('Y-m-d H:i:s'),
            'timezone' => 'Asia/Kolkata',
            'waitlist_enabled' => '1',
            'pricing_type' => 'free',
            'status' => 'draft',
        ];

        $this->actingAs($admin)->from(route('web.admin.events.index'))
            ->post(route('web.admin.events.store'), $base + [
                'booking_audience' => 'gym_members',
                'app_visibility' => 'all_atlas',
                'public_booking_enabled' => '0',
            ])->assertSessionHasErrors('booking_audience');

        $this->post(route('web.admin.events.store'), $base + [
            'booking_audience' => 'atlas_members',
            'app_visibility' => 'link_only',
            'public_booking_enabled' => '0',
        ])->assertSessionHasErrors('app_visibility');

        $this->assertDatabaseMissing('events', ['title' => 'Invalid event']);
    }

    private function user(RoleName $role): User
    {
        $user = User::factory()->create([
            'active_role' => $role->value,
            'is_active' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function event(User $creator, ?Gym $gym = null): Event
    {
        return Event::query()->create([
            'scope' => $gym ? 'gym' : 'global',
            'booking_audience' => 'anyone',
            'app_visibility' => $gym ? 'hosting_gym' : 'all_atlas',
            'public_booking_enabled' => true,
            'gym_id' => $gym?->id,
            'created_by_user_id' => $creator->id,
            'title' => 'Community Strength Workshop',
            'description' => 'A coached strength and mobility session.',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
            'timezone' => 'Asia/Kolkata',
            'capacity' => 25,
            'waitlist_enabled' => true,
            'pricing_type' => 'free',
            'currency' => 'INR',
            'status' => 'published',
            'published_at' => now(),
            'location_name' => 'Main Studio',
        ]);
    }
}
