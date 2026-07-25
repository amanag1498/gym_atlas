<?php

namespace App\Http\Requests\Diet;

class UpdateDietPlanRequest extends StoreDietPlanRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['member_ids'], $rules['member_ids.*'], $rules['gym_id'], $rules['branch_id']);

        return $rules;
    }
}
