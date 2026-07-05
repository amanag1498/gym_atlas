<?php

namespace App\Http\Requests\Billing;

use App\Enums\DiscountType;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMemberMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'member_id' => ['required', 'integer', 'exists:users,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:'.implode(',', MembershipStatus::values())],
            'custom_fee_enabled' => ['sometimes', 'boolean'],
            'custom_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:'.implode(',', DiscountType::values())],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'custom_joining_fee' => ['nullable', 'numeric', 'min:0'],
            'joining_fee_waived' => ['sometimes', 'boolean'],
            'partial_month_fee' => ['nullable', 'numeric', 'min:0'],
            'pt_custom_fee' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'initial_payment_mode' => ['nullable', 'in:'.implode(',', PaymentMode::values())],
            'paid_at' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string'],
            'allow_overpayment' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_status' => ['nullable', 'in:'.implode(',', PaymentStatus::values())],
            'custom_fee_reason' => ['nullable', 'string'],
            'approved_by_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->boolean('custom_fee_enabled') && blank($this->input('custom_fee_reason'))) {
                $validator->errors()->add('custom_fee_reason', 'The custom fee reason field is required when a custom fee is enabled.');
            }
        });
    }
}
