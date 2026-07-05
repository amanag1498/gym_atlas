<?php

namespace App\Http\Requests\Web\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'inquiry_type' => ['required', 'in:user,gym,trainer,support'],
            'message' => ['required', 'string', 'max:4000'],
            'redirect_to' => ['nullable', 'string', 'max:255'],
        ];
    }
}
