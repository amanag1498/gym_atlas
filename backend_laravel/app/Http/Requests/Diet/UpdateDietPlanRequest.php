<?php

namespace App\Http\Requests\Diet;

use Illuminate\Validation\Rule;

class UpdateDietPlanRequest extends StoreDietPlanRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['member_ids'], $rules['member_ids.*'], $rules['gym_id'], $rules['branch_id']);
        $rules['meals.*.items.*.food_catalog_item_id'] = [
            'nullable',
            'integer',
            Rule::exists('food_catalog_items', 'id'),
        ];

        return $rules;
    }
}
