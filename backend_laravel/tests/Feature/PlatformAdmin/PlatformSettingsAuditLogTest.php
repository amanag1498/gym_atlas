<?php

namespace Tests\Feature\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Gym;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_platform_admin_can_save_settings_from_web_and_api(): void
    {
        $admin = User::factory()->create([
            'email' => 'settings-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $this->post('/admin/login', [
            'email' => 'settings-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->put(route('web.admin.settings.update'), [
            'support_email' => 'support@example.com',
            'support_phone' => '9999999999',
            'privacy_policy_url' => 'https://example.com/privacy',
            'terms_url' => 'https://example.com/terms',
            'default_commission_percentage' => '12.5',
            'promoted_listing_price' => '2999',
            'featured_listing_price' => '4999',
            'app_banners_placeholder' => 'Summer banners go live next week.',
            'feature_flags_placeholder' => '{"beta_discovery": true}',
        ])->assertRedirect();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'support_email',
        ]);
        $this->assertSame(
            'support@example.com',
            PlatformSetting::query()->where('key', 'support_email')->firstOrFail()->value['value']
        );

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.support_email', 'support@example.com');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/platform-admin/settings', [
                'support_email' => 'support-2@example.com',
                'feature_flags_placeholder' => 'discovery_v2=off',
            ])
            ->assertOk()
            ->assertJsonPath('data.support_email', 'support-2@example.com');

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'web.platform.settings.updated',
            'actor_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'platform.settings.updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_platform_admin_can_view_and_filter_sanitized_audit_logs(): void
    {
        $admin = User::factory()->create([
            'email' => 'audit-admin@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'active_role' => RoleName::PlatformAdmin->value,
        ]);
        $admin->assignRole(RoleName::PlatformAdmin->value);

        $otherActor = User::factory()->create(['is_active' => true]);
        $gym = Gym::query()->create([
            'owner_user_id' => $admin->id,
            'name' => 'Audit Gym',
            'slug' => 'audit-gym',
            'city' => 'Mumbai',
            'status' => 'active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $admin->id,
            'user_id' => $admin->id,
            'gym_id' => $gym->id,
            'event' => 'platform.gym.updated',
            'action' => 'update',
            'actor_role' => RoleName::PlatformAdmin->value,
            'subject_type' => Gym::class,
            'subject_id' => $gym->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser',
            'old_values' => ['password' => 'secret', 'status' => 'pending'],
            'new_values' => ['token' => 'abc123', 'status' => 'active'],
            'occurred_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        ActivityLog::query()->create([
            'actor_user_id' => $otherActor->id,
            'user_id' => $otherActor->id,
            'event' => 'platform.user.created',
            'action' => 'create',
            'actor_role' => RoleName::GymOwner->value,
            'subject_type' => User::class,
            'subject_id' => $otherActor->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'Another Agent',
            'old_values' => null,
            'new_values' => ['name' => 'Secondary'],
            'occurred_at' => now()->subDays(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->post('/admin/login', [
            'email' => 'audit-admin@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('web.admin.dashboard'));

        $this->get(route('web.admin.audit-logs.index', [
            'actor' => $admin->name,
            'action' => 'update',
            'gym' => $gym->id,
        ]))
            ->assertOk()
            ->assertSee('Audit Logs')
            ->assertSee('Audit Gym')
            ->assertSee('[redacted]')
            ->assertDontSee('abc123')
            ->assertDontSee('Secondary');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/platform-admin/audit-logs?action=update&gym='.$gym->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.action', 'update')
            ->assertJsonPath('data.0.gym.name', 'Audit Gym')
            ->assertJsonPath('data.0.old_values.password', '[redacted]')
            ->assertJsonPath('data.0.new_values.token', '[redacted]');
    }
}
