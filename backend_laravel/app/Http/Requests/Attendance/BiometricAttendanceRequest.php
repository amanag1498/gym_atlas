<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class BiometricAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'biometric_identifier' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source_device' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'biometric_identifier' => filled($this->input('biometric_identifier'))
                ? trim((string) $this->input('biometric_identifier'))
                : null,
        ]);
    }
}
