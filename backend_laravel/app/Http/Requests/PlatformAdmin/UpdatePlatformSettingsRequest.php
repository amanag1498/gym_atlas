<?php

namespace App\Http\Requests\PlatformAdmin;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Platform\PlatformSettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'transactional_email_enabled' => ['sometimes', 'boolean'],
            'demo_login_enabled' => ['sometimes', 'boolean'],
            'demo_member_login_email' => ['nullable', 'email', 'max:255'],
            'demo_trainer_login_email' => ['nullable', 'email', 'max:255'],
            'maintenance_mode_enabled' => ['sometimes', 'boolean'],
            'maintenance_title' => ['sometimes', 'required', 'string', 'max:120'],
            'maintenance_message' => ['sometimes', 'required', 'string', 'max:500'],
            'force_upgrade_enabled' => ['sometimes', 'boolean'],
            'member_android_min_build' => ['sometimes', 'required', 'integer', 'min:1'],
            'member_android_min_version' => ['sometimes', 'required', 'string', 'max:40'],
            'member_android_store_url' => ['nullable', 'url', 'max:2048'],
            'member_ios_min_build' => ['sometimes', 'required', 'integer', 'min:1'],
            'member_ios_min_version' => ['sometimes', 'required', 'string', 'max:40'],
            'member_ios_store_url' => ['nullable', 'url', 'max:2048'],
            'trainer_android_min_build' => ['sometimes', 'required', 'integer', 'min:1'],
            'trainer_android_min_version' => ['sometimes', 'required', 'string', 'max:40'],
            'trainer_android_store_url' => ['nullable', 'url', 'max:2048'],
            'trainer_ios_min_build' => ['sometimes', 'required', 'integer', 'min:1'],
            'trainer_ios_min_version' => ['sometimes', 'required', 'string', 'max:40'],
            'trainer_ios_store_url' => ['nullable', 'url', 'max:2048'],
            'upgrade_title' => ['sometimes', 'required', 'string', 'max:120'],
            'upgrade_message' => ['sometimes', 'required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $effective = array_merge(app(PlatformSettingService::class)->all(), $this->all());

            if (filter_var($effective['demo_login_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $memberEmail = mb_strtolower(trim((string) ($effective['demo_member_login_email'] ?? '')));
                $trainerEmail = mb_strtolower(trim((string) ($effective['demo_trainer_login_email'] ?? '')));

                if ($memberEmail === '' && $trainerEmail === '') {
                    $validator->errors()->add('demo_member_login_email', 'Configure at least one reviewer email before enabling demo login.');
                } else {
                    $this->validateDemoUser($validator, 'demo_member_login_email', $memberEmail, RoleName::Member->value);
                    $this->validateDemoUser($validator, 'demo_trainer_login_email', $trainerEmail, RoleName::Trainer->value);
                }
            }

            if (! filter_var($effective['force_upgrade_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            foreach (['member_android', 'member_ios', 'trainer_android', 'trainer_ios'] as $target) {
                $minimumBuild = (int) ($effective["{$target}_min_build"] ?? 1);
                $storeUrl = trim((string) ($effective["{$target}_store_url"] ?? ''));
                if ($minimumBuild > 1 && $storeUrl === '') {
                    $validator->errors()->add(
                        "{$target}_store_url",
                        'Add the published store URL before requiring this app build.',
                    );
                }
            }
        });
    }

    private function validateDemoUser(Validator $validator, string $field, string $email, string $role): void
    {
        if ($email === '') {
            return;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || ! $user->hasRole($role)) {
            $validator->errors()->add($field, "Choose an existing {$role} user email for demo login.");
        }
    }
}
