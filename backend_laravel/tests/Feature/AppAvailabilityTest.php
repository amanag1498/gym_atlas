<?php

namespace Tests\Feature;

use App\Http\Requests\PlatformAdmin\UpdatePlatformSettingsRequest as ApiSettingsRequest;
use App\Http\Requests\Web\Platform\UpdatePlatformSettingsRequest as WebSettingsRequest;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
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

    public function test_force_upgrade_does_not_apply_mobile_store_versions_to_flutter_web_or_desktop(): void
    {
        $this->setSetting('force_upgrade_enabled', true);
        $this->setSetting('member_android_min_build', 99);

        foreach (['web', 'desktop'] as $platform) {
            $headers = [
                'X-Atlas-App' => 'member',
                'X-Client-Platform' => $platform,
                'X-App-Version-Code' => '1',
            ];

            $this->withHeaders($headers)
                ->getJson('/api/public/app-config')
                ->assertOk()
                ->assertJsonPath('data.platform', $platform)
                ->assertJsonPath('data.update_required', false)
                ->assertJsonPath('data.store_url', null);

            $this->withHeaders($headers)
                ->getJson('/api/public/discovery/gyms')
                ->assertOk();
        }
    }

    public function test_settings_reject_an_enforced_build_without_its_store_url(): void
    {
        foreach ([ApiSettingsRequest::class, WebSettingsRequest::class] as $requestClass) {
            $input = [
                'force_upgrade_enabled' => true,
                'member_ios_min_build' => 2,
            ];
            $request = $requestClass::create('/settings', 'PUT', $input);
            $request->setContainer($this->app);
            $validator = Validator::make($input, $request->rules());
            $request->withValidator($validator);

            $this->assertTrue($validator->fails());
            $this->assertArrayHasKey('member_ios_store_url', $validator->errors()->toArray());

            $validInput = [
                ...$input,
                'member_ios_store_url' => 'https://apps.apple.com/app/id123456789',
            ];
            $validRequest = $requestClass::create('/settings', 'PUT', $validInput);
            $validRequest->setContainer($this->app);
            $validValidator = Validator::make($validInput, $validRequest->rules());
            $validRequest->withValidator($validValidator);

            $this->assertFalse($validValidator->fails());
        }
    }

    private function setSetting(string $key, mixed $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }
}
