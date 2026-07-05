<?php

namespace App\Http\Requests\Billing;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'member_membership_id' => ['nullable', 'integer'],
            'custom_fee_enabled' => ['required', 'boolean'],
            'custom_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'in:'.implode(',', DiscountType::values())],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'custom_joining_fee' => ['nullable', 'numeric', 'min:0'],
            'joining_fee_waived' => ['sometimes', 'boolean'],
            'partial_month_fee' => ['nullable', 'numeric', 'min:0'],
            'pt_custom_fee' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'custom_fee_reason' => ['nullable', 'string'],
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
