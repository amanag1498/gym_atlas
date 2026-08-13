@php
    $isEdit = isset($event) && $event->exists;
    $timezone = old('timezone', $event->timezone ?? (($gym ?? null)?->timezone ?? config('app.timezone')));
    $dateValue = static fn ($value) => $value?->timezone($timezone)->format('Y-m-d\\TH:i');
    $selectedStatus = old('status', $event->status ?? 'published');
    $selectedPricing = old('pricing_type', $event->pricing_type ?? 'free');
@endphp

<form method="POST" action="{{ $formAction }}" class="space-y-5" data-event-form>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if ($panel === 'gym')
        <input type="hidden" name="gym_id" value="{{ $gym->id }}">
        @if (! $isEdit && isset($branch) && $branch)
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
        @endif
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(300px,0.65fr)]">
        <div class="space-y-5">
            <div class="panel-card-muted p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                        <i class="ti ti-calendar-event text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Event identity</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">The title, category, host, and cover members see in the event roster.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-form-input name="title" label="Event title" :value="$event->title ?? null" placeholder="Morning Zumba" required />
                    <x-form-input name="category" label="Category" :value="$event->category ?? null" placeholder="Zumba, workshop, community" />
                    <x-form-select name="host_user_id" label="Event host" :selected="$event->host_user_id ?? null" :options="['' => 'No named host'] + $hosts->pluck('name', 'id')->all()" />
                    <x-form-input name="cover_image_url" label="Cover image URL" type="url" :value="$event->cover_image_url ?? null" placeholder="https://..." />
                    <div class="md:col-span-2">
                        <label for="description" class="panel-label">Description</label>
                        <textarea id="description" name="description" rows="4" class="panel-textarea" placeholder="What members should know before reserving a spot">{{ old('description', $event->description ?? null) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="panel-card-muted p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                        <i class="ti ti-clock text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Schedule and booking window</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Times are interpreted in the selected timezone and stored consistently for app reminders.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-form-input name="starts_at" label="Starts" type="datetime-local" :value="$isEdit ? $dateValue($event->starts_at) : null" required />
                    <x-form-input name="ends_at" label="Ends" type="datetime-local" :value="$isEdit ? $dateValue($event->ends_at) : null" required />
                    <x-form-input name="timezone" label="Timezone" :value="$timezone" placeholder="Asia/Kolkata" required />
                    <x-form-input name="booking_opens_at" label="Booking opens" type="datetime-local" :value="$isEdit ? $dateValue($event->booking_opens_at) : null" />
                    <x-form-input name="booking_closes_at" label="Booking closes" type="datetime-local" :value="$isEdit ? $dateValue($event->booking_closes_at) : null" />
                    <x-form-input name="cancellation_closes_at" label="Cancellation closes" type="datetime-local" :value="$isEdit ? $dateValue($event->cancellation_closes_at) : null" />
                </div>
            </div>

            <div class="panel-card-muted p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <i class="ti ti-map-pin text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Venue and directions</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Add human-readable venue details and optional coordinates for one-tap directions in the apps.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <x-form-input name="location_name" label="Location name" :value="$event->location_name ?? null" placeholder="Studio A" />
                    <x-form-input name="address" label="Address" :value="$event->address ?? null" placeholder="Full venue address" />
                    <x-form-input name="latitude" label="Latitude" type="number" step="0.0000001" :value="$event->latitude ?? null" placeholder="28.6139000" />
                    <x-form-input name="longitude" label="Longitude" type="number" step="0.0000001" :value="$event->longitude ?? null" placeholder="77.2090000" />
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="panel-card-muted p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">
                        <i class="ti ti-ticket text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Reservation settings</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Control availability, overflow, and how payment is communicated.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <x-form-input name="capacity" label="Capacity" type="number" min="1" :value="$event->capacity ?? null" placeholder="Leave empty for unlimited" />
                    <x-form-select name="pricing_type" label="Booking type" :selected="$selectedPricing" :options="['free' => 'Free reservation', 'pay_at_venue' => 'Pay at venue']" data-event-pricing />
                    <div data-event-price-field>
                        <x-form-input name="price_amount" label="Venue price (INR)" type="number" step="0.01" min="0" :value="$event->price_amount ?? null" placeholder="0.00" />
                    </div>
                    <x-form-input name="payment_note" label="Payment note" :value="$event->payment_note ?? null" placeholder="Pay at reception before class" />

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70">
                        <input type="hidden" name="waitlist_enabled" value="0">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="waitlist_enabled" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('waitlist_enabled', $event->waitlist_enabled ?? true))>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Enable waitlist</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Members queue automatically when capacity is full.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="panel-card-muted p-4 sm:p-5">
                <label for="status" class="panel-label">Publishing status</label>
                <select id="status" name="status" class="panel-select">
                    <option value="published" @selected($selectedStatus === 'published')>{{ $isEdit ? 'Published' : 'Publish now' }}</option>
                    <option value="draft" @selected($selectedStatus === 'draft')>{{ $isEdit ? 'Draft' : 'Save as draft' }}</option>
                </select>
                <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Publishing makes the event visible to eligible members and notifies the assigned host.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row xl:flex-col">
                <x-action-button type="submit" class="w-full justify-center">
                    <i class="ti {{ $isEdit ? 'ti-device-floppy' : 'ti-calendar-plus' }}"></i>
                    {{ $isEdit ? 'Save event changes' : 'Create event' }}
                </x-action-button>
                <x-action-button as="a" variant="secondary" class="w-full justify-center" href="{{ $cancelHref }}">Cancel</x-action-button>
            </div>
        </div>
    </div>
</form>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-event-form]').forEach(form => {
                    const pricing = form.querySelector('[data-event-pricing]');
                    const priceField = form.querySelector('[data-event-price-field]');
                    const priceInput = priceField?.querySelector('input');
                    const syncPricing = () => {
                        const isVenuePaid = pricing?.value === 'pay_at_venue';
                        priceField?.classList.toggle('hidden', !isVenuePaid);
                        if (priceInput) priceInput.required = isVenuePaid;
                    };
                    pricing?.addEventListener('change', syncPricing);
                    syncPricing();
                });
            });
        </script>
    @endpush
@endonce
