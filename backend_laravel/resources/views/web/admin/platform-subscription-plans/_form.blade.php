@php
    $periodOptions = [
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'quarter' => 'Quarter',
        'year' => 'Year',
    ];

    $statusOptions = [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
    <x-premium-card class="p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <x-form-input name="name" label="Plan Name" :value="$plan->name" placeholder="Platform Growth" />
                @error('name') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2">
                <x-form-input name="slug" label="Slug" :value="$plan->slug" placeholder="platform-growth" />
                @error('slug') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="panel-label" for="description">Description</label>
                <textarea id="description" name="description" rows="4" class="panel-textarea" placeholder="Short internal summary for finance and operations.">{{ old('description', $plan->description) }}</textarea>
                @error('description') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-select name="status" label="Status" :selected="$plan->status" :options="$statusOptions" />
                @error('status') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="sort_order" label="Sort Order" type="number" :value="$plan->sort_order ?? 0" min="0" />
                @error('sort_order') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-select name="billing_period" label="Billing Period" :selected="$plan->billing_period" :options="$periodOptions" />
                @error('billing_period') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="billing_interval_count" label="Interval Count" type="number" :value="$plan->billing_interval_count ?? 1" min="1" />
                @error('billing_interval_count') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="price" label="Plan Price" type="number" step="0.01" :value="$plan->price ?? 0" min="0" />
                @error('price') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="setup_fee" label="Setup Fee" type="number" step="0.01" :value="$plan->setup_fee ?? 0" min="0" />
                @error('setup_fee') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <x-form-input name="trial_days" label="Trial Days" type="number" :value="$plan->trial_days ?? 0" min="0" />
                @error('trial_days') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $plan->is_default)) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500/20">
                    Default plan for new gym billing assignments
                </label>
            </div>

            <div>
                <label class="panel-label" for="included_services_text">Included Services</label>
                <textarea id="included_services_text" name="included_services_text" rows="6" class="panel-textarea" placeholder="One service per line">{{ old('included_services_text', implode(PHP_EOL, $plan->included_services ?? [])) }}</textarea>
                @error('included_services') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="panel-label" for="feature_highlights_text">Feature Highlights</label>
                <textarea id="feature_highlights_text" name="feature_highlights_text" rows="6" class="panel-textarea" placeholder="One highlight per line">{{ old('feature_highlights_text', implode(PHP_EOL, $plan->feature_highlights ?? [])) }}</textarea>
                @error('feature_highlights') <div class="mt-2 text-sm text-rose-600">{{ $message }}</div> @enderror
            </div>
        </div>
    </x-premium-card>

    <div class="space-y-6">
        <x-premium-card class="p-5">
            <h3 class="panel-section-title">Commercial Preview</h3>
            <div class="mt-4 space-y-3">
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Cadence</span>
                    <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->cadence_label ?: 'Configured on save' }}</span>
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Price</span>
                    <span class="font-semibold text-slate-950 dark:text-white">{{ $plan->price_label ?: '₹0' }}</span>
                </div>
                <div class="panel-card-muted flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-500">Setup fee</span>
                    <span class="font-semibold text-slate-950 dark:text-white">₹{{ number_format((float) ($plan->setup_fee ?? 0), 0) }}</span>
                </div>
            </div>
        </x-premium-card>

        <x-premium-card class="p-5">
            <h3 class="panel-section-title">Admin Scope</h3>
            <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">This module is for what a gym pays to the platform. It is separate from the member membership plans that gyms sell inside their own panel.</p>
        </x-premium-card>

        <div class="flex flex-wrap gap-2">
            <x-action-button type="submit">{{ $submitLabel }}</x-action-button>
            <x-action-button as="a" variant="secondary" href="{{ route('web.admin.platform-subscription-plans.index') }}">Back to Plans</x-action-button>
        </div>
    </div>
</div>
