@php
    $statusOptions = [
        'trialing' => 'Trialing',
        'active' => 'Active',
        'past_due' => 'Past Due',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.18fr)_minmax(320px,0.82fr)]">
    <x-premium-card class="p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="panel-label" for="gym_id">Gym</label>
                <select id="gym_id" name="gym_id" class="panel-select">
                    <option value="">Select gym</option>
                    @foreach ($gyms as $gym)
                        <option value="{{ $gym->id }}" @selected((string) old('gym_id', $subscription->gym_id) === (string) $gym->id)>{{ $gym->name }}{{ $gym->owner?->email ? ' • '.$gym->owner->email : '' }}</option>
                    @endforeach
                </select>
                @error('gym_id') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="panel-label" for="platform_subscription_plan_id">Platform Plan</label>
                <select id="platform_subscription_plan_id" name="platform_subscription_plan_id" class="panel-select">
                    <option value="">Custom billing without template</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string) old('platform_subscription_plan_id', $subscription->platform_subscription_plan_id) === (string) $plan->id)>{{ $plan->name }} • {{ $plan->price_label }}</option>
                    @endforeach
                </select>
                @error('platform_subscription_plan_id') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-select name="status" label="Subscription Status" :selected="$subscription->status" :options="$statusOptions" />
                @error('status') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    <input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew', $subscription->auto_renew ?? true)) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500/20">
                    Auto renew this gym subscription
                </label>
            </div>

            <div>
                <x-form-input name="starts_at" label="Starts At" type="date" :value="optional($subscription->starts_at)->format('Y-m-d') ?? $subscription->starts_at" />
                @error('starts_at') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="renews_at" label="Renews At" type="date" :value="optional($subscription->renews_at)->format('Y-m-d') ?? $subscription->renews_at" />
                @error('renews_at') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="ends_at" label="Ends At" type="date" :value="optional($subscription->ends_at)->format('Y-m-d') ?? $subscription->ends_at" />
                @error('ends_at') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="trial_ends_at" label="Trial Ends At" type="date" :value="optional($subscription->trial_ends_at)->format('Y-m-d') ?? $subscription->trial_ends_at" />
                @error('trial_ends_at') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="billing_amount" label="Billing Amount" type="number" step="0.01" :value="$subscription->billing_amount ?? ''" min="0" />
                @error('billing_amount') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="setup_fee_amount" label="Setup Fee Amount" type="number" step="0.01" :value="$subscription->setup_fee_amount ?? ''" min="0" />
                @error('setup_fee_amount') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="panel-label" for="included_services_text">Services Included for This Gym</label>
                <textarea id="included_services_text" name="included_services_text" rows="5" class="panel-textarea" placeholder="One service per line">{{ old('included_services_text', implode(PHP_EOL, $subscription->included_services ?? [])) }}</textarea>
                @error('included_services') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="panel-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="4" class="panel-textarea" placeholder="Internal billing notes, contract references, escalation context, or manual override details.">{{ old('notes', $subscription->notes) }}</textarea>
                @error('notes') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            @error('trial_ends_at') <div class="md:col-span-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            @error('renews_at') <div class="md:col-span-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            @error('ends_at') <div class="md:col-span-2 text-sm text-rose-600">{{ $message }}</div> @enderror
        </div>
    </x-premium-card>

    <div class="space-y-6">
        <x-premium-card class="p-5">
            <h3 class="panel-section-title">Current Snapshot</h3>
            <div class="mt-4 space-y-3">
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Status</span>
                    <x-status-badge :label="$subscription->status ?? 'active'" />
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Billing</span>
                    <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) ($subscription->billing_amount ?? 0), 0) }}</span>
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Setup fee</span>
                    <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) ($subscription->setup_fee_amount ?? 0), 0) }}</span>
                </div>
            </div>
        </x-premium-card>

        @if (! empty($subscription->plan_snapshot))
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Plan Snapshot</h3>
                <div class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                    <div class="font-semibold text-slate-950 dark:text-white">{{ $subscription->plan_snapshot['name'] ?? 'Plan snapshot' }}</div>
                    <div>{{ $subscription->plan_snapshot['cadence_label'] ?? 'Cadence not captured' }}</div>
                    <div>{{ $subscription->plan_snapshot['price_label'] ?? 'No price snapshot' }}</div>
                </div>
            </x-premium-card>
        @endif

        @if (! empty($recentInvoices) && count($recentInvoices) > 0)
            <x-premium-card class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="panel-section-title">Recent Billing</h3>
                        <p class="panel-section-copy">Latest platform invoices for this gym account.</p>
                    </div>
                    <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.ledger', $subscription) }}">Full Ledger</x-action-button>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach ($recentInvoices as $invoice)
                        <div class="panel-card-muted px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ optional($invoice->period_starts_at)->format('d M Y') ?: 'n/a' }} to {{ optional($invoice->period_ends_at)->format('d M Y') ?: 'n/a' }}
                                    </div>
                                </div>
                                <x-status-badge :label="$invoice->status" />
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-sm">
                                <span class="text-slate-500 dark:text-slate-400">₹{{ number_format((float) $invoice->total_amount, 0) }}</span>
                                <span class="text-slate-500 dark:text-slate-400">
                                    @if ($invoice->paid_at)
                                        Paid {{ $invoice->paid_at->format('d M Y') }}
                                    @else
                                        Due {{ optional($invoice->due_at)->format('d M Y') ?: 'n/a' }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-premium-card>
        @endif

        <div class="flex flex-wrap gap-2">
            <x-action-button type="submit">{{ $submitLabel }}</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.gym-platform-subscriptions.index') }}">Back to Gym Billing</x-action-button>
        </div>
    </div>
</div>
