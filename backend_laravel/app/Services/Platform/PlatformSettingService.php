<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Schema;

class PlatformSettingService
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return $this->defaults();
        }

        $stored = PlatformSetting::query()
            ->get()
            ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $setting->value['value'] ?? null])
            ->all();

        return array_merge($this->defaults(), $stored);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return array_merge($this->defaults(), $values);
        }

        foreach ($values as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        return $this->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'support_email' => null,
            'support_phone' => null,
            'privacy_policy_url' => null,
            'terms_url' => null,
            'default_commission_percentage' => null,
            'promoted_listing_price' => null,
            'featured_listing_price' => null,
            'app_banners_placeholder' => null,
            'feature_flags_placeholder' => null,
            'transactional_email_enabled' => true,
            'demo_login_enabled' => false,
            'demo_member_login_email' => null,
            'demo_trainer_login_email' => null,
            'maintenance_mode_enabled' => false,
            'maintenance_title' => 'A quick tune-up is underway',
            'maintenance_message' => 'Atlas is temporarily unavailable while we make things better. Please try again shortly.',
            'force_upgrade_enabled' => false,
            'member_android_min_build' => 1,
            'member_android_min_version' => '1.0.0',
            'member_android_store_url' => 'https://play.google.com/store/apps/details?id=com.techybugs.gymatlas.member',
            'member_ios_min_build' => 1,
            'member_ios_min_version' => '1.0.0',
            'member_ios_store_url' => null,
            'trainer_android_min_build' => 1,
            'trainer_android_min_version' => '1.0.0',
            'trainer_android_store_url' => 'https://play.google.com/store/apps/details?id=com.techybugs.gymatlas.trainer',
            'trainer_ios_min_build' => 1,
            'trainer_ios_min_version' => '1.0.0',
            'trainer_ios_store_url' => null,
            'upgrade_title' => 'Update Atlas to continue',
            'upgrade_message' => 'A newer version is required to keep your account secure and your experience running smoothly.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicAppConfig(string $appType, string $platform, int $currentBuild = 0): array
    {
        $values = $this->all();
        $appType = in_array($appType, ['member', 'trainer'], true) ? $appType : 'member';
        $platform = in_array($platform, ['android', 'ios'], true) ? $platform : 'android';
        $prefix = "{$appType}_{$platform}";
        $minimumBuild = max(1, (int) ($values["{$prefix}_min_build"] ?? 1));

        return [
            'demo_login_enabled' => (bool) ($values['demo_login_enabled'] ?? false),
            'app_type' => $appType,
            'platform' => $platform,
            'maintenance_mode_enabled' => (bool) ($values['maintenance_mode_enabled'] ?? false),
            'maintenance_title' => (string) ($values['maintenance_title'] ?? 'A quick tune-up is underway'),
            'maintenance_message' => (string) ($values['maintenance_message'] ?? 'Atlas is temporarily unavailable. Please try again shortly.'),
            'force_upgrade_enabled' => (bool) ($values['force_upgrade_enabled'] ?? false),
            'minimum_build_number' => $minimumBuild,
            'minimum_version' => (string) ($values["{$prefix}_min_version"] ?? '1.0.0'),
            'update_title' => (string) ($values['upgrade_title'] ?? 'Update Atlas to continue'),
            'update_message' => (string) ($values['upgrade_message'] ?? 'A newer version is required to continue.'),
            'store_url' => $values["{$prefix}_store_url"] ?? null,
            'update_required' => (bool) ($values['force_upgrade_enabled'] ?? false)
                && $currentBuild > 0
                && $currentBuild < $minimumBuild,
        ];
    }
}
