<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewIndependentTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'verification_status' => strtolower(trim((string) $this->input('verification_status'))),
            'reason' => filled($this->input('reason')) ? trim((string) $this->input('reason')) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::in(['pending', 'verified', 'rejected', 'suspended'])],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('verification_status'), ['rejected', 'suspended'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
