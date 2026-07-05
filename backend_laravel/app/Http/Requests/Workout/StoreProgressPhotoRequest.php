<?php

namespace App\Http\Requests\Workout;

use App\Enums\ProgressPhotoType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgressPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo_url' => ['required', 'url', 'max:2048'],
            'photo_type' => ['nullable', Rule::in(array_column(ProgressPhotoType::cases(), 'value'))],
            'album_key' => ['nullable', 'string', 'max:255'],
            'captured_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
