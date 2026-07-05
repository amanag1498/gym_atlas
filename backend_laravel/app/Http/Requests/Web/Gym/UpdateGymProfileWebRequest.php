<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\UpdateGymProfileRequest;
use App\Support\Scheduling\OperatingHours;

class UpdateGymProfileWebRequest extends UpdateGymProfileRequest
{
    use InteractsWithDelimitedFields;

    protected function prepareForValidation(): void
    {
        $timings = $this->parseJsonArray($this->input('timings_json'));

        $payload = [
            'timings' => $timings,
            'weekly_off' => is_array($timings)
                ? OperatingHours::weeklyOffFromTimings(OperatingHours::normalize($timings))
                : collect($this->parseDelimitedString($this->input('weekly_off_text')))
                    ->map(fn (mixed $value): string => strtolower(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all(),
            'remove_logo' => $this->boolean('remove_logo'),
            'remove_cover_image' => $this->boolean('remove_cover_image'),
            'remove_gallery_photo_ids' => collect((array) $this->input('remove_gallery_photo_ids', []))
                ->map(fn (mixed $value): int => (int) $value)
                ->filter(fn (int $value): bool => $value > 0)
                ->values()
                ->all(),
        ];

        if ($this->has('photo_urls_text')) {
            $payload['photo_urls'] = $this->parseDelimitedString($this->input('photo_urls_text'));
        }

        foreach (['public_listing_enabled', 'show_pricing', 'pricing_visible', 'trial_available', 'contact_visible'] as $field) {
            if ($this->has($field)) {
                $payload[$field] = $this->boolean($field);
            }
        }

        $this->merge($payload);
    }
}
