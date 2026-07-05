<?php

namespace App\Http\Requests\Web\Platform;

use App\Http\Requests\PlatformAdmin\UpdatePlatformGymRequest;
use App\Http\Requests\Web\Gym\InteractsWithDelimitedFields;
use App\Support\Scheduling\OperatingHours;

class UpdatePlatformGymWebRequest extends UpdatePlatformGymRequest
{
    use InteractsWithDelimitedFields;

    protected function prepareForValidation(): void
    {
        $timings = $this->parseJsonArray($this->input('timings_json'));

        $this->merge([
            'owner_user_id' => $this->filled('owner_user_id') ? (int) $this->input('owner_user_id') : null,
            'facility_ids' => array_values(array_filter((array) $this->input('facility_ids', []))),
            'timings' => $timings,
            'weekly_off' => is_array($timings)
                ? OperatingHours::weeklyOffFromTimings(OperatingHours::normalize($timings))
                : $this->parseDelimitedString($this->input('weekly_off_text')),
            'public_listing_enabled' => $this->boolean('public_listing_enabled'),
            'show_pricing' => $this->boolean('show_pricing'),
            'trial_available' => $this->boolean('trial_available'),
            'contact_visible' => $this->boolean('contact_visible'),
            'assign_platform_subscription' => $this->boolean('assign_platform_subscription'),
            'platform_subscription_auto_renew' => $this->boolean('platform_subscription_auto_renew'),
            'platform_subscription_included_services' => $this->normalizeSubscriptionList($this->input('platform_subscription_included_services_text')),
            'remove_gallery_photo_ids' => collect((array) $this->input('remove_gallery_photo_ids', []))
                ->map(fn (mixed $value): int => (int) $value)
                ->filter(fn (int $value): bool => $value > 0)
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'assign_platform_subscription' => ['sometimes', 'boolean'],
            'platform_subscription_plan_id' => ['nullable', 'exists:platform_subscription_plans,id'],
            'platform_subscription_status' => ['nullable', 'in:trialing,active,past_due,cancelled,expired'],
            'platform_subscription_starts_at' => ['nullable', 'date'],
            'platform_subscription_renews_at' => ['nullable', 'date'],
            'platform_subscription_ends_at' => ['nullable', 'date'],
            'platform_subscription_trial_ends_at' => ['nullable', 'date'],
            'platform_subscription_billing_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_subscription_setup_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_subscription_auto_renew' => ['nullable', 'boolean'],
            'platform_subscription_included_services' => ['nullable', 'array'],
            'platform_subscription_included_services.*' => ['string', 'max:255'],
            'platform_subscription_notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSubscriptionList(mixed $value): array
    {
        $items = preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: [];

        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
