<?php

namespace Tests\Feature;

use App\Models\Gym;
use Database\Seeders\PublicGymDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicGymDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_idempotent_public_demo_gym_profiles_without_user_accounts(): void
    {
        Storage::fake('public');

        $this->seed(PublicGymDemoSeeder::class);
        $this->seed(PublicGymDemoSeeder::class);

        $gyms = Gym::query()->where('slug', 'like', 'atlas-demo-%')->get();

        $this->assertCount(6, $gyms);
        $this->assertTrue($gyms->every(fn (Gym $gym): bool => $gym->owner_user_id === null));
        $this->assertTrue($gyms->every(fn (Gym $gym): bool => $gym->public_listing_enabled && $gym->public_listing_approval_status === 'approved'));
        $this->assertTrue($gyms->every(fn (Gym $gym): bool => $gym->branches()->count() === 1 && $gym->membershipPlans()->count() === 2));

        $this->get(route('public.gyms.index'))->assertOk()->assertSee('Ember Athletic Club');
        $this->get(route('public.gyms.show', 'atlas-demo-ember-athletic-club'))->assertOk()->assertSee('Studio Access');
    }
}
