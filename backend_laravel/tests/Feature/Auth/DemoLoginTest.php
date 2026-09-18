<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_app_config_exposes_demo_enabled_without_reviewer_emails(): void
    {
        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_member_login_email', 'member-reviewer@example.com');
        $this->setPlatformSetting('demo_trainer_login_email', 'trainer-reviewer@example.com');

        $this->getJson('/api/public/app-config')
            ->assertOk()
            ->assertJsonPath('data.demo_login_enabled', true)
            ->assertJsonMissingPath('data.demo_member_login_email')
            ->assertJsonMissingPath('data.demo_trainer_login_email');
    }

    public function test_demo_login_is_rejected_when_disabled(): void
    {
        $this->setPlatformSetting('demo_login_enabled', false);
        $this->setPlatformSetting('demo_member_login_email', 'member-reviewer@example.com');

        $this->postJson('/api/public/auth/demo/login', [
            'email' => 'member-reviewer@example.com',
            'app_type' => RoleName::Member->value,
        ])->assertForbidden()
            ->assertJsonPath('errors.code', 'demo_login_unavailable');
    }

    public function test_demo_login_rejects_an_email_that_does_not_match_configuration(): void
    {
        User::factory()->create(['email' => 'member-reviewer@example.com']);
        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_member_login_email', 'member-reviewer@example.com');

        $this->postJson('/api/public/auth/demo/login', [
            'email' => 'someone-else@example.com',
            'app_type' => RoleName::Member->value,
        ])->assertForbidden()
            ->assertJsonPath('errors.code', 'demo_login_unavailable');
    }

    public function test_demo_login_rejects_a_configured_user_without_the_requested_app_role(): void
    {
        $user = User::factory()->create([
            'email' => 'trainer-reviewer@example.com',
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Trainer->value);

        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_member_login_email', 'trainer-reviewer@example.com');

        $this->postJson('/api/public/auth/demo/login', [
            'email' => 'trainer-reviewer@example.com',
            'app_type' => RoleName::Member->value,
        ])->assertForbidden()
            ->assertJsonPath('errors.code', 'demo_login_unavailable');
    }

    public function test_demo_login_rejects_an_inactive_demo_account(): void
    {
        $user = User::factory()->create([
            'email' => 'member-reviewer@example.com',
            'active_role' => RoleName::Member->value,
            'is_active' => false,
        ]);
        $user->assignRole(RoleName::Member->value);

        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_member_login_email', 'member-reviewer@example.com');

        $this->postJson('/api/public/auth/demo/login', [
            'email' => 'member-reviewer@example.com',
            'app_type' => RoleName::Member->value,
        ])->assertStatus(423)
            ->assertJsonPath('errors.code', 'demo_account_inactive');
    }

    public function test_demo_login_issues_a_scoped_member_token_for_the_configured_existing_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Member Reviewer',
            'email' => 'member-reviewer@example.com',
            'active_role' => RoleName::Member->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RoleName::Member->value);

        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_member_login_email', 'member-reviewer@example.com');

        $response = $this->postJson('/api/public/auth/demo/login', [
            'email' => 'MEMBER-REVIEWER@example.com',
            'app_type' => RoleName::Member->value,
            'device_name' => 'app-review',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'member-reviewer@example.com')
            ->assertJsonPath('data.user.active_role', RoleName::Member->value);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'app-review',
            'abilities' => '["role:member"]',
        ]);
    }

    public function test_demo_login_issues_a_scoped_trainer_token_for_the_configured_existing_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Trainer Reviewer',
            'email' => 'trainer-reviewer@example.com',
            'active_role' => RoleName::Trainer->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RoleName::Trainer->value);

        $this->setPlatformSetting('demo_login_enabled', true);
        $this->setPlatformSetting('demo_trainer_login_email', 'trainer-reviewer@example.com');

        $response = $this->postJson('/api/public/auth/demo/login', [
            'email' => 'trainer-reviewer@example.com',
            'app_type' => RoleName::Trainer->value,
            'device_name' => 'trainer-review',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'trainer-reviewer@example.com')
            ->assertJsonPath('data.user.active_role', RoleName::Trainer->value);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'trainer-review',
            'abilities' => '["role:trainer"]',
        ]);
    }

    private function setPlatformSetting(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
