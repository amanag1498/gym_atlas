<?php

namespace App\Http\Requests\Trainer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnTrainerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_photo_url' => ['nullable', 'url', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'certifications' => ['nullable', 'array'],
            'certifications.*' => ['nullable'],
            'certifications.*.name' => ['nullable', 'string', 'max:255'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:255'],
            'certifications.*.issued_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'certifications.*.file_url' => ['nullable', 'url', 'max:2048'],
            'certifications.*.file_name' => ['nullable', 'string', 'max:255'],
            'certifications.*.mime_type' => ['nullable', 'string', 'max:120'],
            'certifications.*.file_size' => ['nullable', 'integer', 'min:0'],
            'certifications.*.file_type' => ['nullable', 'string', 'max:30'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:120'],
            'availability_notes' => ['nullable', 'string', 'max:5000'],
            'trainer_onboarding_step' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'trainer_onboarding_completed' => ['sometimes', 'boolean'],
        ];
    }
}
