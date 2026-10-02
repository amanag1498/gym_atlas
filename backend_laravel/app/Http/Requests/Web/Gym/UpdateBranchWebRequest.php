<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\UpdateBranchRequest;
use App\Support\Scheduling\OperatingHours;

class UpdateBranchWebRequest extends UpdateBranchRequest
{
    use InteractsWithDelimitedFields;

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_photo_indexes' => ['nullable', 'array'],
            'remove_photo_indexes.*' => ['integer', 'min:0'],
        ]);
    }

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
        ];

        if ($this->has('photo_urls_text')) {
            $payload['photo_urls'] = $this->parseDelimitedString($this->input('photo_urls_text'));
        }

        if ($this->boolean('facility_ids_present') && ! $this->has('facility_ids')) {
            $payload['facility_ids'] = [];
        }

        $this->merge($payload);

        parent::prepareForValidation();
    }
}
