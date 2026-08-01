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
            'biometric_identifier' => ['required_without:qr_payload', 'nullable', 'string', 'max:255'],
            'qr_payload' => ['required_without:biometric_identifier', 'nullable', 'string', 'max:4096'],
            'notes' => ['nullable', 'string'],
            'source_device' => ['nullable', 'string', 'max:255'],
        ];
    }
}
