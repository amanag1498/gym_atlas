<?php

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertTrainerSpecializationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $specializationId = $this->route('trainerSpecialization')?->id ?? $this->route('trainerSpecialization');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('trainer_specializations', 'name')->ignore($specializationId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
