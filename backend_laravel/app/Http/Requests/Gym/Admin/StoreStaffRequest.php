<?php

namespace App\Http\Requests\Gym\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Schema;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['required', Rule::in([RoleName::BranchManager->value, RoleName::GymStaff->value])],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => ['string', Rule::in([
                'view_billing',
                'collect_payment',
                'edit_custom_fee',
                'manage_attendance',
                'manage_members',
                'manage_trainers',
                'send_announcements',
                'view_reports',
                'manage_staff',
            ])],
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status')) {
            $this->merge([
                'is_active' => $this->input('status') === 'active',
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('existing_user_id')) {
                if (! $this->filled('name')) {
                    $validator->errors()->add('name', 'The name field is required when no existing user is selected.');
                }

                if (! $this->filled('email')) {
                    $validator->errors()->add('email', 'The email field is required when no existing user is selected.');
                }
            }
        });
    }
}
