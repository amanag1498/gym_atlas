@php
    $isEdit = isset($event) && $event->exists;
    $timezone = old('timezone', $event->timezone ?? (($gym ?? null)?->timezone ?? config('app.timezone')));
    $dateValue = static fn ($value) => $value?->timezone($timezone)->format('Y-m-d\\TH:i');
    $selectedStatus = old('status', $event->status ?? 'published');
    $selectedPricing = old('pricing_type', $event->pricing_type ?? 'free');
    $selectedAudience = old('booking_audience', $event->booking_audience ?? ($panel === 'admin' ? 'atlas_members' : 'gym_members'));
    $selectedVisibility = old('app_visibility', $event->app_visibility ?? ($panel === 'admin' ? 'all_atlas' : 'hosting_gym'));
    $audienceOptions = $panel === 'admin'
        ? ['atlas_members' => 'Any Atlas member', 'anyone' => 'Anyone with the public link']
        : ['gym_members' => 'Members of the hosting gym', 'atlas_members' => 'Any Atlas member with the link', 'anyone' => 'Anyone with the public link'];
    $visibilityOptions = $panel === 'admin'
        ? ['all_atlas' => 'All Atlas member apps', 'link_only' => 'Link only']
        : ['hosting_gym' => 'Hosting gym member app', 'link_only' => 'Link only'];
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="space-y-5" data-event-form>
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
                    <div>
                        <label for="cover_image" class="panel-label">Event cover image</label>
                        @if($isEdit && $event->cover_image_url)
                            <img src="{{ $event->cover_image_url }}" alt="{{ $event->title }} cover" class="mb-3 h-36 w-full rounded-2xl border border-slate-200 object-cover dark:border-slate-700">
                        @endif
                        <input id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/webp" class="panel-input block w-full file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                        <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">JPG, PNG, or WebP. Minimum 600×300, maximum 8 MB. Atlas optimizes and stores the image.</p>
                        @error('cover_image')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                        @if($isEdit && $event->cover_image_url)
                            <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input type="hidden" name="remove_cover_image" value="0">
                                <input type="checkbox" name="remove_cover_image" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                Remove current cover image
                            </label>
                        @endif
                    </div>
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
                    <x-form-select name="booking_audience" label="Who can book?" :selected="$selectedAudience" :options="$audienceOptions" data-event-audience />
                    <p class="-mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $panel === 'gym' ? 'Atlas members outside this gym can book only after opening the event link; the event is never broadcast to all Member apps.' : 'This controls eligibility. Booking never enrolls an attendee into a gym.' }}</p>

                    <x-form-select name="app_visibility" label="Where it appears" :selected="$selectedVisibility" :options="$visibilityOptions" data-event-visibility />

                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/70" data-public-booking-control>
                        <input type="hidden" name="public_booking_enabled" value="0">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="public_booking_enabled" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('public_booking_enabled', $event->public_booking_enabled ?? false)) data-public-booking-checkbox>
                            <span>
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Accept public-link bookings</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400" data-public-booking-help>Guests can reserve without an Atlas account. Their booking does not create an Atlas profile or gym membership.</span>
                            </span>
                        </label>
                    </div>

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
                    const audience = form.querySelector('[data-event-audience]');
                    const publicBooking = form.querySelector('[data-public-booking-checkbox]');
                    const publicBookingHelp = form.querySelector('[data-public-booking-help]');
                    const syncPricing = () => {
                        const isVenuePaid = pricing?.value === 'pay_at_venue';
                        priceField?.classList.toggle('hidden', !isVenuePaid);
                        if (priceInput) priceInput.required = isVenuePaid;
                    };
                    const syncPublicBooking = () => {
                        if (!publicBooking) return;
                        const available = audience?.value === 'anyone';
                        publicBooking.disabled = !available;
                        if (!available) publicBooking.checked = false;
                        if (publicBookingHelp) {
                            publicBookingHelp.textContent = available
                                ? 'Guests can reserve without an Atlas account. Their booking does not create an Atlas profile or gym membership.'
                                : 'Choose “Anyone with the public link” to accept guest bookings.';
                        }
                    };
                    pricing?.addEventListener('change', syncPricing);
                    audience?.addEventListener('change', syncPublicBooking);
                    syncPricing();
                    syncPublicBooking();
                });
            });
        </script>
    @endpush
@endonce
