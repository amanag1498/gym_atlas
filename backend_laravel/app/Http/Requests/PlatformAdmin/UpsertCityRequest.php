<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
