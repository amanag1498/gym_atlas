<?php

namespace App\Http\Requests\Billing;

class UpdateMembershipPlanRequest extends StoreMembershipPlanRequest
{
    public function rules(): array
    {
        return [
            'gym_id' => ['sometimes', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'billing_type' => ['sometimes', 'in:free,paid'],
            'billing_period' => ['sometimes', 'in:day,week,month,quarter,year,custom'],
            'billing_interval_count' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'duration_days' => ['sometimes', 'integer', 'min:1'],
            'plan_price' => ['sometimes', 'numeric', 'min:0'],
            'joining_fee' => ['sometimes', 'numeric', 'min:0'],
            'pt_included' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }
}
