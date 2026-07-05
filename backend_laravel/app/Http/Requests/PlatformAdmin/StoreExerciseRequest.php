<?php

namespace App\Http\Requests\PlatformAdmin;

use App\Enums\ExerciseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'muscle_group' => ['required', 'string', 'max:255'],
            'secondary_muscles' => ['nullable', 'array'],
            'secondary_muscles.*' => ['string', 'max:255'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'video_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', Rule::in(array_column(ExerciseStatus::cases(), 'value'))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
