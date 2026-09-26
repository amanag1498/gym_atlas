<?php

namespace App\Http\Requests\SmartAttendance;

use App\Services\SmartAttendance\SmartAttendanceHubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSmartAttendanceHubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:160'],
            'platform' => ['required', 'string', Rule::in(SmartAttendanceHubService::PLATFORMS)],
            'firmware_version' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
