<?php

namespace App\Http\Requests\SmartAttendance;

use Illuminate\Foundation\Http\FormRequest;

class SmartAttendanceHubHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firmware_version' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
            'battery_percent' => ['nullable', 'integer', 'between:0,100'],
            'ble_advertising' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata', []);
        if (! is_array($metadata)) {
            $metadata = [];
        }
        foreach (['battery_percent', 'ble_advertising'] as $field) {
            if ($this->has($field)) {
                $metadata[$field] = $this->input($field);
            }
        }
        $this->merge(['metadata' => $metadata === [] ? null : $metadata]);
    }
}
