<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListPublicGymRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'distance' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'city' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', 'max:120'],
            'verified_only' => ['nullable', 'boolean'],
            'featured_only' => ['nullable', 'boolean'],
            'personal_training_available' => ['nullable', 'boolean'],
            'women_friendly' => ['nullable', 'boolean'],
            'women_only' => ['nullable', 'boolean'],
            'open_now' => ['nullable', 'boolean'],
            'trial_available' => ['nullable', 'boolean'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (($this->filled('distance') || $this->routeIs('public.discovery.nearby'))
                && (! $this->filled('latitude') || ! $this->filled('longitude'))) {
                $validator->errors()->add('latitude', 'Latitude and longitude are required for nearby gym discovery.');
            }

            if ($this->filled('min_price') && $this->filled('max_price') && (float) $this->input('min_price') > (float) $this->input('max_price')) {
                $validator->errors()->add('min_price', 'The minimum price cannot be greater than the maximum price.');
            }
        });
    }
}
