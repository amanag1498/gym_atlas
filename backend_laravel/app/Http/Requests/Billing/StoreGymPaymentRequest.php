<?php

namespace App\Http\Requests\Billing;

class StoreGymPaymentRequest extends StorePaymentRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'member_membership_id' => ['required', 'integer', 'exists:member_memberships,id'],
        ];
    }
}
