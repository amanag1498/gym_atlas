<?php

namespace App\Http\Requests\Gym\Admin;

use App\Http\Requests\Concerns\ValidatesOperatingHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGymProfileRequest extends FormRequest
{
    use ValidatesOperatingHours;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'cover_image_url' => ['nullable', 'url', 'max:2048'],
            'remove_cover_image' => ['sometimes', 'boolean'],
            'photo_urls' => ['nullable', 'array', 'max:10'],
            'photo_urls.*' => ['string', 'url', 'max:2048'],
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_gallery_photo_ids' => ['nullable', 'array'],
            'remove_gallery_photo_ids.*' => ['integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'instagram_profile' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'city' => ['required', 'string', 'max:120'],
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
            'weekly_off.*' => ['string', Rule::in([
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday',
            ])],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
            'public_listing_enabled' => ['sometimes', 'boolean'],
            'show_pricing' => ['sometimes', 'boolean'],
            'pricing_visible' => ['sometimes', 'boolean'],
            'trial_available' => ['sometimes', 'boolean'],
            'contact_visible' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address', $this->input('address_line'));
        $showPricingInput = $this->has('show_pricing')
            ? $this->input('show_pricing')
            : $this->input('pricing_visible');

        $this->merge([
            'address' => $address,
            'address_line' => $address,
            'show_pricing' => $showPricingInput,
            'pricing_visible' => $showPricingInput,
            'public_listing_enabled' => $this->has('public_listing_enabled')
                ? $this->boolean('public_listing_enabled')
                : $this->input('public_listing_enabled'),
            'trial_available' => $this->has('trial_available')
                ? $this->boolean('trial_available')
                : $this->input('trial_available'),
            'contact_visible' => $this->has('contact_visible')
                ? $this->boolean('contact_visible')
                : $this->input('contact_visible'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOperatingHoursFields($validator, ['timings']));
    }
}
