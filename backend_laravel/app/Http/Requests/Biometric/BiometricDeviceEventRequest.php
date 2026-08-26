<?php

namespace App\Http\Requests\Biometric;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BiometricDeviceEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'string', 'max:191', 'required_without:occurred_at'],
            'external_user_id' => ['required', 'string', 'max:120'],
            'event_type' => ['sometimes', 'string', Rule::in(['check_in', 'check_out', 'access_granted'])],
            'modality' => ['nullable', 'string', Rule::in(['face', 'fingerprint', 'palm', 'card', 'pin', 'unknown'])],
            'direction' => ['nullable', 'string', Rule::in(['in', 'out', 'unknown'])],
            'occurred_at' => ['required', 'date'],
            'image' => ['prohibited'],
            'face_image' => ['prohibited'],
            'fingerprint_template' => ['prohibited'],
            'biometric_template' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'external_user_id' => filled($this->input('external_user_id')) ? trim((string) $this->input('external_user_id')) : null,
        ]);
    }
}
