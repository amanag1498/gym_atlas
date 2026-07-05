<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignMemberTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_trainer_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
