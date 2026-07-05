<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignTrainerMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
