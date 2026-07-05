<?php

namespace App\Http\Requests\Billing;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;

class MarkMembershipPaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['nullable', 'in:'.implode(',', PaymentMode::values())],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
