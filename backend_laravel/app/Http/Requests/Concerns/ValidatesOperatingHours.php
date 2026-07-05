<?php

namespace App\Http\Requests\Concerns;

use App\Support\Scheduling\OperatingHours;
use Illuminate\Validation\Validator;

trait ValidatesOperatingHours
{
    /**
     * @param  list<string>  $fields
     */
    protected function validateOperatingHoursFields(Validator $validator, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $this->input($field);

            if ($value === '__invalid_json__') {
                $validator->errors()->add($field, 'The operating hours payload is not valid JSON.');
                continue;
            }

            foreach (OperatingHours::validationErrors($value) as $error) {
                $validator->errors()->add($field, $error);
            }
        }
    }
}
