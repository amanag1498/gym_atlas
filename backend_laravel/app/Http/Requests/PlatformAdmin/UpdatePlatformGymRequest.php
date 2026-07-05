<?php

namespace App\Http\Requests\PlatformAdmin;

use App\Http\Requests\Concerns\ValidatesOperatingHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformGymRequest extends FormRequest
{
    use ValidatesOperatingHours;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:40'],
            'instagram_profile' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i'],
            'timings' => ['nullable', 'array'],
            'weekly_off' => ['nullable', 'array'],
            'weekly_off.*' => ['string', 'max:20'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
            'public_listing_enabled' => ['sometimes', 'boolean'],
            'show_pricing' => ['sometimes', 'boolean'],
            'trial_available' => ['sometimes', 'boolean'],
            'contact_visible' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['pending', 'active', 'rejected', 'inactive', 'suspended'])],
            'rejected_reason' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_gallery_photo_ids' => ['nullable', 'array'],
            'remove_gallery_photo_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOperatingHoursFields($validator, ['timings']));
    }
}
