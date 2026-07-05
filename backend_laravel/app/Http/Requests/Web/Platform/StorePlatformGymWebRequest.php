<?php

namespace App\Http\Requests\Web\Platform;

use App\Http\Requests\PlatformAdmin\StorePlatformGymRequest;
use App\Http\Requests\Web\Gym\InteractsWithDelimitedFields;
use App\Support\Scheduling\OperatingHours;

class StorePlatformGymWebRequest extends StorePlatformGymRequest
{
    use InteractsWithDelimitedFields;

    protected function prepareForValidation(): void
    {
        $ownerMode = $this->input('owner_mode', $this->filled('owner_user_id') ? 'existing' : 'new');
        $timings = $this->parseJsonArray($this->input('timings_json'));
        $branchTimings = $this->parseJsonArray($this->input('branch_timings_json'));

        $this->merge([
            'owner_user_id' => $ownerMode === 'existing' && $this->filled('owner_user_id') ? (int) $this->input('owner_user_id') : null,
            'facility_ids' => array_values(array_filter((array) $this->input('facility_ids', []))),
            'timings' => $timings,
            'weekly_off' => is_array($timings)
                ? OperatingHours::weeklyOffFromTimings(OperatingHours::normalize($timings))
                : $this->parseDelimitedString($this->input('weekly_off_text')),
            'branch_timings' => $branchTimings,
            'branch_weekly_off' => is_array($branchTimings)
                ? OperatingHours::weeklyOffFromTimings(OperatingHours::normalize($branchTimings))
                : $this->parseDelimitedString($this->input('branch_weekly_off_text')),
            'public_listing_enabled' => $this->boolean('public_listing_enabled'),
            'show_pricing' => $this->boolean('show_pricing'),
            'trial_available' => $this->boolean('trial_available'),
            'contact_visible' => $this->boolean('contact_visible'),
            'create_default_branch' => $this->boolean('create_default_branch'),
            'branch_same_as_gym' => $this->boolean('branch_same_as_gym'),
            'assign_platform_subscription' => $this->boolean('assign_platform_subscription'),
            'platform_subscription_auto_renew' => $this->boolean('platform_subscription_auto_renew'),
            'platform_subscription_included_services' => $this->normalizeSubscriptionList($this->input('platform_subscription_included_services_text')),
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
