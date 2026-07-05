<?php

namespace App\Http\Requests\Trial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTrialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['pending', 'accepted', 'rejected', 'completed', 'converted'])],
            'assigned_trainer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'preferred_date' => ['sometimes', 'date'],
            'preferred_time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->validated() === []) {
                $validator->errors()->add('request', 'At least one field must be provided.');
            }
        });
    }
}
