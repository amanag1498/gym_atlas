<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\UpdateBranchRequest;
use App\Support\Scheduling\OperatingHours;

class UpdateBranchWebRequest extends UpdateBranchRequest
{
    use InteractsWithDelimitedFields;

    protected function prepareForValidation(): void
    {
        $timings = $this->parseJsonArray($this->input('timings_json'));

        $this->merge([
            'photo_urls' => $this->parseDelimitedString($this->input('photo_urls_text')),
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
