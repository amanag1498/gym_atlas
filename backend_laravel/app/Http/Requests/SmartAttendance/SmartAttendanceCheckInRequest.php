<?php

namespace App\Http\Requests\SmartAttendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmartAttendanceCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'hub_public_id' => ['nullable', 'required_without:hub_id', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'],
            'hub_id' => ['nullable', 'required_without:hub_public_id', 'integer', 'min:1', 'max:4294967295'],
            'protocol_version' => ['required', 'integer', Rule::in([1, 2, 3])],
            'rssi' => ['nullable', 'integer', 'between:-127,20'],
            'detected_at' => ['nullable', 'date', 'after_or_equal:-6 hours', 'before_or_equal:+5 minutes'],
            'source' => ['nullable', 'string', 'max:80'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('hub_public_id')) {
            $this->merge([
                'hub_public_id' => strtoupper(trim((string) $this->input('hub_public_id'))),
            ]);
        }
    }
}
