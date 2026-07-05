<?php

namespace App\Http\Requests\Gym\Admin;

use App\Http\Requests\Concerns\ValidatesOperatingHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBranchRequest extends FormRequest
{
    use ValidatesOperatingHours;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch')?->id ?? $this->route('branch');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', Rule::unique('branches', 'slug')->ignore($branchId)],
            'address' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'timezone'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'timings' => ['nullable', 'array'],
            'weekly_off' => ['nullable', 'array'],
            'weekly_off.*' => ['string', 'max:20'],
            'photo_urls' => ['nullable', 'array', 'max:10'],
            'photo_urls.*' => ['string', 'url', 'max:2048'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address', $this->input('address_line'));

        $this->merge([
            'address' => $address,
            'address_line' => $address,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : $this->input('is_active'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOperatingHoursFields($validator, ['timings']));
    }
}
