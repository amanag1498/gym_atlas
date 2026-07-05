<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymListingFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'is_featured' => ['sometimes', 'boolean'],
            'is_promoted' => ['sometimes', 'boolean'],
        ];
    }
}
