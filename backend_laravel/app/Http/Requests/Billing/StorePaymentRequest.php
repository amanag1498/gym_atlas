<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['required', 'in:'.implode(',', PaymentMode::values())],
            'paid_at' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'allow_overpayment' => ['sometimes', 'boolean'],
        ];
    }
}
