<?php

namespace App\Http\Requests\Web\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymSelfEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'consent' => $this->boolean('consent'),
            'whatsapp_marketing_consent' => $this->boolean('whatsapp_marketing_consent'),
            'fitness_goal_ids' => array_values(array_filter((array) $this->input('fitness_goal_ids', []))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fitness_goal_ids' => ['required', 'array', 'min:1'],
            'fitness_goal_ids.*' => ['integer', 'exists:fitness_goals,id'],
            'gender' => ['nullable', 'string', 'max:40'],
            'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'height_cm' => ['required', 'numeric', 'between:120,230'],
            'weight_kg' => ['required', 'numeric', 'between:30,180'],
            'injury_notes' => ['nullable', 'string', 'max:5000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'consent' => ['accepted'],
            'whatsapp_marketing_consent' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
