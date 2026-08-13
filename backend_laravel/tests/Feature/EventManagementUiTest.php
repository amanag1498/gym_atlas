<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Event;
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
            ->assertSee('Event schedule')
            ->assertSee($event->title);

        $this->get(route('web.admin.events.show', $event))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Event details')
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
            ->assertSee('Event schedule')
            ->assertSee($event->title);

        $this->get(route('web.gym.events.show', ['gym' => $gym->id, 'event' => $event]))
            ->assertOk()
            ->assertSee('Confirmed')
            ->assertSee('Event details')
            ->assertSee('Attendee and waitlist roster');

        $this->get(route('web.gym.events.edit', ['gym' => $gym->id, 'event' => $event]))
            ->assertOk()
            ->assertSee('Edit event')
            ->assertSee('Event identity')
            ->assertSee('Reservation settings');
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
