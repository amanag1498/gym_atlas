<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('member')?->id ?? $this->route('member');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($memberId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'url', 'max:2048'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'assigned_trainer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'fitness_goal' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:40'],
            'height_cm' => ['nullable', 'numeric', 'between:0,500'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
            'experience_level' => ['nullable', 'string', 'max:120'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
            'injury_notes' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'biometric_identifier' => ['nullable', 'string', 'max:255', Rule::unique('member_profiles', 'biometric_identifier')->ignore($this->route('member')?->memberProfile?->id)],
            'biometric_enabled' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
            'membership_status' => ['nullable', 'string', 'max:80'],
            'membership_expires_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ] + (Schema::hasColumn('users', 'phone') ? [
            'phone' => ['nullable', 'string', 'max:30'],
        ] : []);
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if ($status !== null) {
            $this->merge([
                'membership_status' => $status,
                'is_active' => $status === 'active',
            ]);
        }

        if (! $this->filled('biometric_identifier')) {
            $this->merge([
                'biometric_enabled' => false,
            ]);
        }
    }
}
