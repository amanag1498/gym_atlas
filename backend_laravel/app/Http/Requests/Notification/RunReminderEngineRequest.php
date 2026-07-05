<?php

namespace App\Http\Requests\Notification;

use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;

class RunReminderEngineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'in:'.implode(',', ReminderType::values())],
            'gym_id' => ['nullable', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
