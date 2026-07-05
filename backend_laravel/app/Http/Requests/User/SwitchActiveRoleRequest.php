<?php

namespace App\Http\Requests\User;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchActiveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active_role' => ['required', 'string', Rule::in(RoleName::values())],
        ];
    }
}
