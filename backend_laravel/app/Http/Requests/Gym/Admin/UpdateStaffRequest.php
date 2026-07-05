<?php

namespace App\Http\Requests\Gym\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff')?->id ?? $this->route('staff');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staffId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['sometimes', Rule::in([RoleName::BranchManager->value, RoleName::GymStaff->value])],
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
        ] + (Schema::hasColumn('users', 'phone') ? [
                'phone' => ['nullable', 'string', 'max:30'],
        ] : []);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status')) {
            $this->merge([
                'is_active' => $this->input('status') === 'active',
            ]);
        }
    }
}
