<?php

namespace App\Http\Requests\Web\Gym;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'entry_type' => ['required', Rule::in(['expense', 'other_income', 'refund', 'adjustment'])],
            'adjustment_direction' => ['nullable', Rule::in(['inflow', 'outflow'])],
            'category' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['nullable', Rule::in(['cash', 'upi', 'card', 'bank'])],
            'reference' => ['nullable', 'string', 'max:160'],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
