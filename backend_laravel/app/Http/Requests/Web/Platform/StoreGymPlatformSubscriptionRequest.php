<?php

namespace App\Http\Requests\Web\Platform;

use App\Models\GymPlatformSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymPlatformSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_renew' => $this->boolean('auto_renew'),
            'included_services' => $this->normalizeList($this->input('included_services_text')),
        ]);
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'exists:gyms,id'],
            'platform_subscription_plan_id' => ['nullable', 'exists:platform_subscription_plans,id'],
            'status' => ['required', Rule::in(GymPlatformSubscription::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'renews_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'billing_amount' => ['nullable', 'numeric', 'min:0'],
            'setup_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'auto_renew' => ['nullable', 'boolean'],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $startsAt = $this->input('starts_at');
                $trialEndsAt = $this->input('trial_ends_at');
                $renewsAt = $this->input('renews_at');
                $endsAt = $this->input('ends_at');

                if ($startsAt && $trialEndsAt && $trialEndsAt < $startsAt) {
                    $validator->errors()->add('trial_ends_at', 'Trial end date must be on or after the start date.');
                }

                if ($startsAt && $renewsAt && $renewsAt < $startsAt) {
                    $validator->errors()->add('renews_at', 'Renewal date must be on or after the start date.');
                }

                if ($startsAt && $endsAt && $endsAt < $startsAt) {
                    $validator->errors()->add('ends_at', 'End date must be on or after the start date.');
                }
            },
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
