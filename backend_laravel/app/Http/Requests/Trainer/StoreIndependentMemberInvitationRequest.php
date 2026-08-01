<?php

namespace App\Http\Requests\Trainer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndependentMemberInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            'sharing_permissions' => ['nullable', 'array'],
            'sharing_permissions.*' => [
                'string',
                Rule::in(['profile', 'workouts', 'diets', 'progress', 'chat']),
            ],
        ];
    }
}
