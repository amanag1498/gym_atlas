<?php

namespace App\Http\Requests\Web\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreGymTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_type' => ['nullable', 'string', 'in:trial,contact'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
