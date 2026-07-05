<?php

namespace App\Http\Requests\Workout;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeightLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
