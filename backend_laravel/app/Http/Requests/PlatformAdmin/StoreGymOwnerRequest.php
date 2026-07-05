<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreGymOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
    }
}
