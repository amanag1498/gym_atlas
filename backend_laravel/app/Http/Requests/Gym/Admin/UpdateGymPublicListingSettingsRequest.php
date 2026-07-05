<?php

namespace App\Http\Requests\Gym\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymPublicListingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'public_listing_enabled' => ['sometimes', 'boolean'],
            'show_pricing' => ['sometimes', 'boolean'],
            'pricing_visible' => ['sometimes', 'boolean'],
            'trial_available' => ['sometimes', 'boolean'],
            'contact_visible' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $showPricingInput = $this->has('show_pricing')
            ? $this->input('show_pricing')
            : $this->input('pricing_visible');

        $this->merge([
            'public_listing_enabled' => $this->has('public_listing_enabled')
                ? $this->boolean('public_listing_enabled')
                : $this->input('public_listing_enabled'),
            'show_pricing' => $showPricingInput,
            'pricing_visible' => $showPricingInput,
            'trial_available' => $this->has('trial_available')
                ? $this->boolean('trial_available')
                : $this->input('trial_available'),
            'contact_visible' => $this->has('contact_visible')
                ? $this->boolean('contact_visible')
                : $this->input('contact_visible'),
        ]);
    }
}
