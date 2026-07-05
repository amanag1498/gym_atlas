<?php

namespace App\Http\Requests\Trainer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainerMemberNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in(['private_to_trainer', 'gym_admin_visible'])],
            'follow_up_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
