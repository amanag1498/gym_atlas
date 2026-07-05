<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMemberDailyStepsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'steps' => ['required', 'integer', 'min:0', 'max:100000'],
            'goalSteps' => ['required', 'integer', 'min:1000', 'max:100000'],
            'distanceMeters' => ['nullable', 'integer', 'min:0'],
            'caloriesEstimated' => ['nullable', 'integer', 'min:0'],
            'source' => ['required', 'string', Rule::in(['healthkit', 'health_connect', 'android_sensor', 'manual'])],
        ];
    }
}
