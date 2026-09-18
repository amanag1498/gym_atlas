<?php

namespace App\Http\Requests\Web\Platform;

use App\Enums\RoleName;
use App\Models\User;
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('demo_login_enabled')) {
                return;
            }

            $memberEmail = mb_strtolower(trim((string) $this->input('demo_member_login_email', '')));
            $trainerEmail = mb_strtolower(trim((string) $this->input('demo_trainer_login_email', '')));

            if ($memberEmail === '' && $trainerEmail === '') {
                $validator->errors()->add('demo_member_login_email', 'Configure at least one reviewer email before enabling demo login.');

                return;
            }

            $this->validateDemoUser($validator, 'demo_member_login_email', $memberEmail, RoleName::Member->value);
            $this->validateDemoUser($validator, 'demo_trainer_login_email', $trainerEmail, RoleName::Trainer->value);
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
