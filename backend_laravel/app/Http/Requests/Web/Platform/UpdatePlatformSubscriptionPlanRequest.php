<?php

namespace App\Http\Requests\Web\Platform;

use App\Models\PlatformSubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePlatformSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var PlatformSubscriptionPlan|null $plan */
        $plan = $this->route('platformSubscriptionPlan');

        $this->merge([
            'slug' => Str::slug((string) $this->input('slug', $this->input('name', $plan?->name))),
            'is_default' => $this->boolean('is_default'),
            'included_services' => $this->normalizeList($this->input('included_services_text')),
            'feature_highlights' => $this->normalizeList($this->input('feature_highlights_text')),
        ]);
    }

    public function rules(): array
    {
        /** @var PlatformSubscriptionPlan|null $plan */
        $plan = $this->route('platformSubscriptionPlan');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('platform_subscription_plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in(PlatformSubscriptionPlan::STATUSES)],
            'billing_period' => ['required', Rule::in(PlatformSubscriptionPlan::BILLING_PERIODS)],
            'billing_interval_count' => ['required', 'integer', 'min:1', 'max:36'],
            'price' => ['required', 'numeric', 'min:0'],
            'setup_fee' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['string', 'max:255'],
            'feature_highlights' => ['nullable', 'array'],
            'feature_highlights.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $value): array
    {
        $items = preg_split('/\r\n|\r|\n/', trim((string) $value)) ?: [];

        return collect($items)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
