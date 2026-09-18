<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_config_selects_member_android_build_and_reports_required_upgrade(): void
    {
        $this->setSetting('force_upgrade_enabled', true);
        $this->setSetting('member_android_min_build', 13);
        $this->setSetting('member_android_min_version', '1.1.0');
        $this->setSetting('member_android_store_url', 'https://play.google.com/store/apps/details?id=com.techybugs.gymatlas.member');

        $this->getJson('/api/public/app-config?app_type=member&platform=android&build_number=12')
            ->assertOk()
            ->assertJsonPath('data.force_upgrade_enabled', true)
            ->assertJsonPath('data.minimum_build_number', 13)
            ->assertJsonPath('data.minimum_version', '1.1.0')
            ->assertJsonPath('data.update_required', true);
    }

    public function test_app_config_keeps_member_and_trainer_minimum_builds_separate(): void
    {
        $this->setSetting('force_upgrade_enabled', true);
        $this->setSetting('member_android_min_build', 20);
        $this->setSetting('trainer_android_min_build', 10);

        $this->getJson('/api/public/app-config?app_type=trainer&platform=android&build_number=11')
            ->assertOk()
            ->assertJsonPath('data.minimum_build_number', 10)
            ->assertJsonPath('data.update_required', false);
    }

    public function test_maintenance_blocks_mobile_api_but_keeps_app_config_available(): void
    {
        $this->setSetting('maintenance_mode_enabled', true);
        $this->setSetting('maintenance_title', 'Back soon');

        $this->postJson('/api/public/auth/firebase/login', [])
            ->assertStatus(503)
            ->assertJsonPath('errors.code', 'maintenance_mode')
            ->assertJsonPath('errors.title', 'Back soon');

        $this->getJson('/api/public/app-config?app_type=member&platform=android&build_number=12')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode_enabled', true);
    }

    public function test_force_upgrade_rejects_old_build_and_accepts_minimum_build(): void
    {
        $this->setSetting('force_upgrade_enabled', true);
        $this->setSetting('trainer_android_min_build', 12);

        $headers = [
            'X-Atlas-App' => 'trainer',
            'X-Client-Platform' => 'android',
            'X-App-Version-Code' => '11',
        ];

        $this->withHeaders($headers)
            ->postJson('/api/public/auth/firebase/login', [])
            ->assertStatus(426)
            ->assertJsonPath('errors.code', 'app_upgrade_required')
            ->assertJsonPath('errors.minimum_build_number', 12);

        $this->withHeaders([...$headers, 'X-App-Version-Code' => '12'])
            ->postJson('/api/public/auth/firebase/login', [])
            ->assertStatus(422);
    }

    public function test_force_upgrade_rejects_legacy_mobile_requests_without_version_headers(): void
    {
        $this->setSetting('force_upgrade_enabled', true);

        $this->postJson('/api/public/auth/firebase/login', [])
            ->assertStatus(426)
            ->assertJsonPath('errors.code', 'app_upgrade_required');
    }

    public function test_force_upgrade_does_not_block_public_web_discovery_without_app_headers(): void
    {
        $this->setSetting('force_upgrade_enabled', true);

        $this->getJson('/api/public/discovery/gyms')->assertOk();
    }

    private function setSetting(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
