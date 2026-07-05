<?php

namespace App\Http\Requests\Billing;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;

class RenewMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:'.implode(',', MembershipStatus::values())],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'initial_payment_mode' => ['nullable', 'in:'.implode(',', PaymentMode::values())],
            'paid_at' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string'],
            'allow_overpayment' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
