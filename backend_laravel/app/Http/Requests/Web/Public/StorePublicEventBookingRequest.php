<?php

namespace App\Http\Requests\Web\Public;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicEventBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9() .-]{7,32}$/'],
            'answers' => ['nullable', 'array', 'max:30'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
