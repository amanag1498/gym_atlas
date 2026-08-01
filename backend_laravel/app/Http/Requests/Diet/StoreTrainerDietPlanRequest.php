<?php

namespace App\Http\Requests\Diet;

class StoreTrainerDietPlanRequest extends StoreDietPlanRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['gym_id'], $rules['branch_id']);
        $rules['independent_trainer_member_relationship_id'] = [
            'nullable',
            'integer',
            'exists:independent_trainer_member_relationships,id',
        ];

        return $rules;
    }
}
