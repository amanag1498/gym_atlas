<?php

namespace App\Http\Requests\Web\Admin;

use App\Http\Requests\Workout\StoreWorkoutBookRequest;
use Illuminate\Validation\Validator;

class UpsertWorkoutBookWebRequest extends StoreWorkoutBookRequest
{
    private bool $plansJsonInvalid = false;

    protected function prepareForValidation(): void
    {
        $payload = $this->input('plans_json');

        if (! is_string($payload) || trim($payload) === '') {
            $this->plansJsonInvalid = true;

            return;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $this->merge([
                'plans' => $decoded,
                'is_featured' => $this->boolean('is_featured'),
            ]);
        } catch (\JsonException) {
            $this->plansJsonInvalid = true;
        }
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->plansJsonInvalid) {
            $validator->after(function (Validator $validator): void {
                $validator->errors()->add(
                    'plans_json',
                    'Plans JSON must be valid and match the workout book schema.',
                );
            });
        }
    }
}
