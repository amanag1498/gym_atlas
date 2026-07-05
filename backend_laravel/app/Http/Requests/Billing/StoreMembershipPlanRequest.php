<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'billing_type' => ['required', Rule::in(['free', 'paid'])],
            'billing_period' => ['required', Rule::in(['day', 'week', 'month', 'quarter', 'year', 'custom'])],
            'billing_interval_count' => ['required', 'integer', 'min:1', 'max:24'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'plan_price' => ['required', 'numeric', 'min:0'],
            'joining_fee' => ['required', 'numeric', 'min:0'],
            'pt_included' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $plan = $this->route('plan');
        $billingType = (string) ($this->input('billing_type') ?: ($plan?->billing_type ?: (((float) ($plan?->plan_price ?? $this->input('plan_price', 0))) <= 0 ? 'free' : 'paid')));
        $billingPeriod = (string) ($this->input('billing_period') ?: ($plan?->billing_period ?: 'custom'));
        $intervalCount = max(1, (int) ($this->input('billing_interval_count') ?: ($plan?->billing_interval_count ?: 1)));
        $rawDurationDays = $this->input('duration_days', $plan?->duration_days);
        $durationDays = $this->resolveDurationDays(
            $billingPeriod,
            $intervalCount,
            $rawDurationDays
        );

        $this->merge([
            'billing_type' => $billingType,
            'billing_period' => $billingPeriod,
            'billing_interval_count' => $intervalCount,
            'duration_days' => $durationDays,
            'plan_price' => $billingType === 'free' ? 0 : $this->input('plan_price', $plan?->plan_price ?? 0),
            'joining_fee' => $billingType === 'free'
                ? 0
                : $this->input('joining_fee', $plan?->joining_fee ?? 0),
            'pt_included' => $this->boolean('pt_included', (bool) ($plan?->pt_included ?? false)),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('billing_period') === 'custom' && ! $this->filled('duration_days')) {
                $validator->errors()->add('duration_days', 'Duration days are required for a custom billing period.');
            }

            if ($this->input('billing_type') === 'paid' && (float) $this->input('plan_price', 0) <= 0) {
                $validator->errors()->add('plan_price', 'Paid plans must have a price greater than zero.');
            }
        });
    }

    private function resolveDurationDays(string $billingPeriod, int $intervalCount, mixed $durationDays): ?int
    {
        return match ($billingPeriod) {
            'day' => $intervalCount,
            'week' => $intervalCount * 7,
            'month' => $intervalCount * 30,
            'quarter' => $intervalCount * 90,
            'year' => $intervalCount * 365,
            default => ($durationDays === null || $durationDays === '') ? null : max(1, (int) $durationDays),
        };
    }
}
