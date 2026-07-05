<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Billing\StorePaymentRequest;

class StorePaymentWebRequest extends StorePaymentRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'member_membership_id' => ['required', 'integer', 'exists:member_memberships,id'],
        ];
    }
}
