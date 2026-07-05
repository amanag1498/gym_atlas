<?php

namespace App\Http\Requests\Web\Gym;

use App\Http\Requests\Gym\Admin\UpdateTrainerRequest;

class UpdateTrainerWebRequest extends UpdateTrainerRequest
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
}
