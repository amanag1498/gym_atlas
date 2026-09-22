<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Gym;
use App\Models\GymPlatformSubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use App\Services\Authorization\ScopeResolver;
use App\Services\Discovery\GymDiscoveryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformSubscriptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformBillingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seedPlatformSubscriptionsForFixtureGyms = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_gym_scope_and_public_discovery_require_a_current_subscription(): void
    {
        $owner = User::factory()->create(['active_role' => RoleName::GymOwner->value]);
        $owner->assignRole(RoleName::GymOwner->value);
        $gym = Gym::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Billing Gate Gym',
            'slug' => 'billing-gate-gym',
            'is_active' => true,
            'status' => 'active',
            'operational_access_enabled' => true,
            'approval_status' => 'approved',
            'public_listing_enabled' => true,
            'public_listing_approval_status' => 'approved',
        ]);
        $owner->gyms()->attach($gym->id, ['role_name' => RoleName::GymOwner->value, 'status' => 'active']);

        $this->assertFalse(app(ScopeResolver::class)->canAccessGym($owner, $gym));
        $this->assertSame(0, app(GymDiscoveryService::class)->list([])->total());
        $this->actingAs($owner, 'sanctum')->getJson('/api/gym/dashboard', ['X-Gym-Id' => (string) $gym->id])->assertForbidden();

        $subscription = GymPlatformSubscription::query()->create([
            'gym_id' => $gym->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'renews_at' => now()->addDays(3)->toDateString(),
            'billing_amount' => 1999,
        ]);

        $this->assertTrue(app(ScopeResolver::class)->canAccessGym($owner, $gym));
        $this->assertSame(1, app(GymDiscoveryService::class)->list([])->total());

        $scheduledSubscription = GymPlatformSubscription::query()->create([
            'gym_id' => $gym->id,
            'status' => 'active',
            'starts_at' => now()->addDays(4)->toDateString(),
            'renews_at' => now()->addMonth()->toDateString(),
            'billing_amount' => 1999,
        ]);
        $this->assertTrue(app(ScopeResolver::class)->canAccessGym($owner, $gym));

        $this->travel(4)->days();
        $this->assertTrue(app(ScopeResolver::class)->canAccessGym($owner, $gym));
        $this->assertSame(1, app(GymDiscoveryService::class)->list([])->total());

        $subscription->update(['renews_at' => now()->addDays(3)->toDateString()]);
        $scheduledSubscription->update(['status' => 'cancelled']);
        $this->assertTrue(app(ScopeResolver::class)->canAccessGym($owner, $gym));

        $subscription->update(['status' => 'past_due']);
        $this->assertFalse(app(ScopeResolver::class)->canAccessGym($owner, $gym));
    }

    public function test_admin_can_assign_complimentary_access_only_with_an_end_date(): void
    {
        $this->seed(PlatformSubscriptionSeeder::class);
        $admin = User::factory()->create(['active_role' => RoleName::PlatformAdmin->value]);
        $admin->assignRole(RoleName::PlatformAdmin->value);
        $gym = Gym::query()->create(['name' => 'Free Period Gym', 'slug' => 'free-period-gym']);
        $plan = PlatformSubscriptionPlan::query()->where('slug', 'complimentary')->sole();
        $endsAt = now()->addDays(7)->toDateString();
        $payload = [
            'gym_id' => $gym->id,
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->toDateString(),
            'billing_amount' => 500,
            'setup_fee_amount' => 100,
            'auto_renew' => 1,
        ];

        $this->actingAs($admin)
            ->post(route('web.admin.gym-platform-subscriptions.store'), $payload)
            ->assertSessionHasErrors('ends_at');

        $this->post(route('web.admin.gym-platform-subscriptions.store'), $payload + ['ends_at' => $endsAt])
            ->assertRedirect();

        $subscription = GymPlatformSubscription::query()->where('gym_id', $gym->id)->sole();
        $this->assertSame('0.00', $subscription->billing_amount);
        $this->assertSame('0.00', $subscription->setup_fee_amount);
        $this->assertFalse($subscription->auto_renew);
        $this->assertSame($endsAt, $subscription->ends_at->toDateString());
        $this->assertSame($endsAt, $subscription->renews_at->toDateString());
        $this->assertTrue($gym->hasPlatformAccess());

        $this->travel(8)->days();
        $this->assertFalse($gym->hasPlatformAccess());
    }
}
