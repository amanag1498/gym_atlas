<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollMemberBiometricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', 'distinct', 'exists:biometric_devices,id'],
            'modalities' => ['required', 'array', 'min:1'],
            'modalities.*' => ['string', 'distinct', Rule::in(['face', 'fingerprint', 'palm', 'card'])],
        ];
    }
}
