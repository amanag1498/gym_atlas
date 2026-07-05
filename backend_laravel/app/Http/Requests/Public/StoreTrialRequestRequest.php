<?php

namespace App\Http\Requests\Public;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTrialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = $this->filled('branch_id')
                ? Branch::query()->find($this->input('branch_id'))
                : null;

            if ($branch && (int) $branch->gym_id !== (int) $this->input('gym_id')) {
                $validator->errors()->add('branch_id', 'The selected branch does not belong to the selected gym.');
            }

            if (! $this->user() && ! $this->filled('name')) {
                $validator->errors()->add('name', 'The name field is required for guest trial requests.');
            }

            if (! $this->filled('phone') && ! $this->filled('email') && ! $this->user()) {
                $validator->errors()->add('phone', 'Either phone or email is required for guest trial requests.');
            }
        });
    }
}
