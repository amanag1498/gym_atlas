<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMemberRequest extends FormRequest
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
            'biometric_identifier' => ['nullable', 'string', 'max:255', Rule::unique('member_profiles', 'biometric_identifier')],
            'biometric_enabled' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'expired'])],
            'membership_status' => ['nullable', 'string', 'max:80'],
            'membership_expires_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'membership_plan_id' => ['nullable', 'integer', 'exists:membership_plans,id'],
            'start_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_date' => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'custom_fee_enabled' => ['sometimes', 'boolean'],
            'custom_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['none', 'fixed', 'percentage'])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'custom_joining_fee' => ['nullable', 'numeric', 'min:0'],
            'joining_fee_waived' => ['sometimes', 'boolean'],
            'partial_month_fee' => ['nullable', 'numeric', 'min:0'],
            'pt_custom_fee' => ['nullable', 'numeric', 'min:0'],
            'custom_fee_reason' => ['nullable', 'string', 'max:5000'],
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
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

            if ($this->filled('membership_plan_id') && ! $this->filled('branch_id')) {
                $validator->errors()->add('branch_id', 'The branch field is required when assigning a membership plan.');
            }

            if ($this->filled('membership_plan_id') && ! $this->filled('start_date')) {
                $validator->errors()->add('start_date', 'The start date field is required when assigning a membership plan.');
            }

            if ($this->filled('membership_plan_id') && $this->boolean('custom_fee_enabled') && blank($this->input('custom_fee_reason'))) {
                $validator->errors()->add('custom_fee_reason', 'The custom fee reason field is required when a custom fee is enabled.');
            }
        });
    }
}
