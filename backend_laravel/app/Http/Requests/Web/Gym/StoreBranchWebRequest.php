<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\StoreBranchRequest;
use App\Support\Scheduling\OperatingHours;

class StoreBranchWebRequest extends StoreBranchRequest
{
    use InteractsWithDelimitedFields;

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $timings = $this->parseJsonArray($this->input('timings_json'));

        $this->merge([
            'photo_urls' => $this->has('photo_urls_text')
                ? $this->parseDelimitedString($this->input('photo_urls_text'))
                : [],
            'timings' => $timings,
            'weekly_off' => is_array($timings)
                ? OperatingHours::weeklyOffFromTimings(OperatingHours::normalize($timings))
                : collect($this->parseDelimitedString($this->input('weekly_off_text')))
                    ->map(fn (mixed $value): string => strtolower(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all(),
        ]);

        parent::prepareForValidation();
    }
}
