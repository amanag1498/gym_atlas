<?php

namespace App\Http\Requests\Diet;

class StoreTrainerDietPlanRequest extends StoreDietPlanRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['gym_id'], $rules['branch_id']);

        return $rules;
    }
}
