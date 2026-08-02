<?php

namespace App\Http\Requests\Gym\Admin;

use App\Models\MemberProfile;
use Illuminate\Foundation\Http\FormRequest;
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
        $gymId = $this->resolvedGymId();
        $memberProfileId = MemberProfile::query()
            ->where('user_id', $memberId)
            ->when($gymId, fn ($query, int $resolvedGymId) => $query->where('gym_id', $resolvedGymId))
            ->value('id');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($memberId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'url', 'max:2048'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
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
            'biometric_identifier' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('member_profiles', 'biometric_identifier')
                    ->where(fn ($query) => $query->where('gym_id', $gymId))
                    ->ignore($memberProfileId),
            ],
            'biometric_enabled' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
            'membership_status' => ['nullable', 'string', 'max:80'],
            'membership_expires_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'biometric_identifier' => filled($this->input('biometric_identifier'))
                ? trim((string) $this->input('biometric_identifier'))
                : null,
        ]);

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

    private function resolvedGymId(): ?int
    {
        $routeGym = $this->route('gym');
        $value = is_object($routeGym) ? $routeGym->id : $routeGym;
        $value ??= $this->input('gym_id');
        $value ??= $this->query('gym');
        $value ??= $this->header('X-Gym-Id');

        return filter_var($value, FILTER_VALIDATE_INT) ?: null;
    }
}
