<?php

namespace App\Http\Requests\PlatformAdmin;

use App\Http\Requests\Concerns\ValidatesOperatingHours;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlatformGymRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'owner_name' => ['required_without:owner_user_id', 'nullable', 'string', 'max:160'],
            'owner_email' => ['required_without:owner_user_id', 'nullable', 'email:rfc', 'max:190', 'unique:users,email'],
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
            'status' => ['required', Rule::in(['pending', 'active'])],
            'create_default_branch' => ['sometimes', 'boolean'],
            'branch_same_as_gym' => ['sometimes', 'boolean'],
            'branch_name' => ['nullable', 'string', 'max:160'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_city' => ['nullable', 'string', 'max:120'],
            'branch_state' => ['nullable', 'string', 'max:120'],
            'branch_pincode' => ['nullable', 'string', 'max:20'],
            'branch_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'branch_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'branch_opening_time' => ['nullable', 'date_format:H:i'],
            'branch_closing_time' => ['nullable', 'date_format:H:i'],
            'branch_timings' => ['nullable', 'array'],
            'branch_weekly_off' => ['nullable', 'array'],
            'branch_weekly_off.*' => ['string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'gallery_images' => ['nullable', 'array', 'max:10'],
            'gallery_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateOperatingHoursFields($validator, ['timings', 'branch_timings']));
    }
}
