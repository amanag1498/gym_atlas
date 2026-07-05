<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\StoreTrainerRequest;
use Illuminate\Validation\Validator;

class StoreTrainerWebRequest extends StoreTrainerRequest
{
    use InteractsWithDelimitedFields;

    protected function prepareForValidation(): void
    {
        $specializations = $this->parseDelimitedString($this->input('specializations_text'));
        if ($specializations === [] && filled($this->input('specialization'))) {
            $specializations = [$this->input('specialization')];
        }

        $this->merge([
            'specializations' => $specializations,
            'certifications' => $this->parseDelimitedString($this->input('certifications_text')),
            'languages' => $this->parseDelimitedString($this->input('languages_text')),
            'is_active' => $this->input('status')
                ? $this->input('status') === 'active'
                : $this->boolean('is_active'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('existing_user_id')) {
                return;
            }

            if (! $this->filled('name')) {
                $validator->errors()->add('name', 'The name field is required when no existing user is selected.');
            }

            if (! $this->filled('email')) {
                $validator->errors()->add('email', 'The email field is required when no existing user is selected.');
            }
        });
    }
}
