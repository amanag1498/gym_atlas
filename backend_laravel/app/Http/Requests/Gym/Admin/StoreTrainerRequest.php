<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTrainerRequest extends FormRequest
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
            'profile_photo_url' => ['nullable', 'url', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:120'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'certifications' => ['nullable', 'array'],
            'certifications.*' => ['string', 'max:255'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:120'],
            'availability_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'verification_status' => ['nullable', 'string', 'max:80'],
        ];

        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $specializations = $this->input('specializations');
        $specialization = $this->input('specialization');

        if (empty($specializations) && filled($specialization)) {
            $specializations = [$specialization];
        }

        $status = $this->input('status');

        $this->merge([
            'specializations' => $specializations,
            'is_active' => $status !== null
                ? $status === 'active'
                : $this->input('is_active', true),
        ]);
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
