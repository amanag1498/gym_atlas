<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $facilityId = $this->route('facility')?->id ?? $this->route('facility');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('facilities', 'name')->ignore($facilityId)],
            'icon' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
