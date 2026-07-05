<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
