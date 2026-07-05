<?php

namespace App\Http\Requests\Web\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:60'],
            'privacy_policy_url' => ['nullable', 'url', 'max:2048'],
            'terms_url' => ['nullable', 'url', 'max:2048'],
            'default_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'promoted_listing_price' => ['nullable', 'numeric', 'min:0'],
            'featured_listing_price' => ['nullable', 'numeric', 'min:0'],
            'app_banners_placeholder' => ['nullable', 'string', 'max:5000'],
            'feature_flags_placeholder' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
