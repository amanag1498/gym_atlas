<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class StoreBodyMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_on' => ['required', 'date'],
            'chest_cm' => ['nullable', 'numeric', 'min:0'],
            'waist_cm' => ['nullable', 'numeric', 'min:0'],
            'hips_cm' => ['nullable', 'numeric', 'min:0'],
            'arm_cm' => ['nullable', 'numeric', 'min:0'],
            'thigh_cm' => ['nullable', 'numeric', 'min:0'],
            'calf_cm' => ['nullable', 'numeric', 'min:0'],
            'body_fat_percentage' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
