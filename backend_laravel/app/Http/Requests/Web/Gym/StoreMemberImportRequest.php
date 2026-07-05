<?php

namespace App\Http\Requests\Web\Gym;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'preview_token' => ['required', 'string'],
        ];
    }
}
