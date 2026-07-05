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
        ];
    }
}
