<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewWorkoutProgressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'override', 'reject', 'disable'])],
            'prescription' => ['required_if:decision,override', 'nullable', 'array'],
            'prescription.sets' => ['nullable', 'integer', 'min:1', 'max:100'],
            'prescription.reps' => ['nullable', 'string', 'max:100'],
            'prescription.target_weight' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
