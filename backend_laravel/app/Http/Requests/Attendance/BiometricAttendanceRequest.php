<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'qr_payload' => filled($this->input('qr_payload'))
                ? trim((string) $this->input('qr_payload'))
                : null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('biometric_identifier') && $this->filled('qr_payload')) {
                $message = 'Provide either a biometric identifier or a QR payload, not both.';
                $validator->errors()->add('biometric_identifier', $message);
                $validator->errors()->add('qr_payload', $message);
            }
        });
    }
}
