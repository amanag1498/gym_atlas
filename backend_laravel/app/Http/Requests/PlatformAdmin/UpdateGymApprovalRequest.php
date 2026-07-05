<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGymApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_status' => ['required', Rule::in(['approved', 'rejected'])],
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
