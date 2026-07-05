<?php

namespace App\Services\Gym;

use App\Models\Gym;
use App\Models\GymSetting;
use Illuminate\Support\Facades\Schema;

class GymSettingService
{
    /**
     * @return array<string, mixed>
     */
    public function all(Gym $gym): array
    {
        if (! Schema::hasTable('gym_settings')) {
            return $this->defaults($gym);
        }

        $stored = GymSetting::query()
            ->where('gym_id', $gym->id)
            ->get()
            ->mapWithKeys(fn (GymSetting $setting) => [$setting->key => $setting->value['value'] ?? null])
            ->all();

        return array_merge($this->defaults($gym), $stored);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function update(Gym $gym, array $values): array
    {
        if (array_key_exists('attendance_duplicate_checkin_rule', $values)) {
            $gym->forceFill([
                'prevent_duplicate_same_day_checkins' => (bool) $values['attendance_duplicate_checkin_rule'],
            ])->save();
        }

        $persistable = collect($values)
            ->except(['attendance_duplicate_checkin_rule'])
            ->all();

        if (! Schema::hasTable('gym_settings')) {
            return array_merge($this->defaults($gym->fresh()), $persistable);
        }

        foreach ($persistable as $key => $value) {
            GymSetting::query()->updateOrCreate(
                ['gym_id' => $gym->id, 'key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        return $this->all($gym->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(Gym $gym): array
    {
        return [
            'attendance_duplicate_checkin_rule' => (bool) $gym->prevent_duplicate_same_day_checkins,
            'billing_settings_placeholder' => null,
            'staff_permission_defaults' => [],
        ];
    }
}
