@php
    use App\Support\Scheduling\OperatingHours;

    $selectedFacilityIds = collect(old('facility_ids', $gym->facilities?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedOwnerId = old('owner_user_id', $gym->owner_user_id);
    $gymStatus = old('status', $gym->status ?? 'pending');
    $gymTimingsValue = old('timings_json')
        ? json_decode((string) old('timings_json'), true)
        : OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $branchTimingsValue = old('branch_timings_json')
        ? json_decode((string) old('branch_timings_json'), true)
        : OperatingHours::normalize([], []);
    $currentPlatformSubscription = $gym->currentPlatformSubscription;
    $assignPlatformSubscription = old('assign_platform_subscription', $currentPlatformSubscription !== null);
    $galleryPreviewPhotos = $gym->relationLoaded('gymPhotos')
        ? $gym->gymPhotos->whereNull('branch_id')->where('type', 'gallery')->sortBy('sort_order')->values()
        : collect();
    $removedGalleryPhotoIds = collect(old('remove_gallery_photo_ids', []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<form id="gym-form" method="POST" action="{{ $isEdit ? route('web.admin.gyms.update', $gym) : route('web.admin.gyms.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
        <div class="space-y-6">
            <x-premium-card class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="panel-section-title">Gym Details</h3>
                            <p class="panel-section-copy">Core identity and operating information.</p>
                        </div>
                        <x-status-badge :label="ucfirst($gymStatus)" />
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <div class="grid gap-4 md:grid-cols-[minmax(0,1.5fr)_minmax(220px,0.8fr)]">
                        <x-form-input name="name" label="Gym Name" :value="old('name', $gym->name)" placeholder="Example: Iron Forge Fitness" required />
                        <x-form-select
                            name="status"
                            label="Status"
                            :selected="$gymStatus"
                            :options="$isEdit
                                ? ['pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended', 'rejected' => 'Rejected']
                                : ['pending' => 'Pending', 'active' => 'Active']"
                        />
                    </div>

                    <div>
                        <label for="description" class="panel-label">Description</label>
                        <textarea id="description" name="description" rows="4" class="panel-textarea" placeholder="Short brand, facility, and audience summary">{{ old('description', $gym->description) }}</textarea>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-form-input name="address" label="Address" :value="old('address', $gym->address ?: $gym->address_line)" placeholder="Street address" />
                        <x-form-input name="city" label="City" :value="old('city', $gym->city)" placeholder="City" required />
                        <x-form-input name="contact_number" label="Contact Number" :value="old('contact_number', $gym->contact_number)" placeholder="+91 98765 43210" />
                        <x-form-input name="instagram_profile" label="Instagram Profile" :value="old('instagram_profile', $gym->instagram_profile)" placeholder="@gymatlas or instagram.com/gymatlas" />
                        <x-form-input name="state" label="State" :value="old('state', $gym->state)" placeholder="State" />
                        <x-form-input name="pincode" label="Pincode" :value="old('pincode', $gym->pincode)" placeholder="Pincode" />
                    </div>

                    <div>
                        <x-admin.operating-hours-editor
                            id="gym_timings_json"
                            name="timings_json"
                            label="Weekly Schedule"
                            :value="$gymTimingsValue"
                            helper="Support split shifts like 05:00 to 10:00 and 17:00 to 22:00, with different hours per day."
                        />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-form-input name="latitude" label="Latitude" :value="old('latitude', $gym->latitude)" placeholder="Latitude" />
                        <x-form-input name="longitude" label="Longitude" :value="old('longitude', $gym->longitude)" placeholder="Longitude" />
                    </div>
                </div>
            </x-premium-card>

            @unless($isEdit)
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="panel-section-title">Default Branch</h3>
                                <p class="panel-section-copy">Create the first branch now so the gym is ready to operate.</p>
                            </div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-brand-300 bg-brand-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-600">
                                Setup
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Create default branch</span>
                                <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Recommended for new gyms.</span>
                            </span>
                            <input class="form-check-input" type="checkbox" id="create_default_branch" name="create_default_branch" value="1" @checked(old('create_default_branch', true))>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-form-input name="branch_name" label="Branch Name" :value="old('branch_name', 'Main Branch')" placeholder="Main Branch" />
                            <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Branch address same as gym</span>
                                    <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Keep the first branch synced to the gym address.</span>
                                </span>
                                <input class="form-check-input" type="checkbox" id="branch_same_as_gym" name="branch_same_as_gym" value="1" @checked(old('branch_same_as_gym', true))>
                            </label>
                            <x-form-input name="branch_address" label="Branch Address" :value="old('branch_address')" placeholder="Branch street address" />
                            <x-form-input name="branch_city" label="Branch City" :value="old('branch_city')" placeholder="Branch city" />
                            <x-form-input name="branch_state" label="Branch State" :value="old('branch_state')" placeholder="Branch state" />
                            <x-form-input name="branch_pincode" label="Branch Pincode" :value="old('branch_pincode')" placeholder="Branch pincode" />
                            <div class="md:col-span-2">
                                <x-admin.operating-hours-editor
                                    id="branch_timings_json"
                                    name="branch_timings_json"
                                    label="Default Branch Schedule"
                                    :value="$branchTimingsValue"
                                    helper="Used only when the default branch should keep its own hours instead of mirroring the gym."
                                />
                            </div>
                        </div>
                    </div>
                </x-premium-card>
            @endunless
        </div>

        <div class="space-y-6">
            @unless($isEdit)
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="panel-section-title">Gym Owner</h3>
                                <p class="panel-section-copy">Link an existing user or create a new owner account.</p>
                            </div>
                            <x-status-badge label="Required" tone="info" />
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <input class="mt-1 h-4 w-4 rounded-full border-slate-300 text-brand-500 focus:ring-brand-500/20" type="radio" name="owner_mode" id="owner_mode_existing" value="existing" @checked(old('owner_mode', $selectedOwnerId ? 'existing' : 'new') === 'existing')>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Use existing user</span>
                                <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Assign the gym to an active platform user.</span>
                            </span>
                        </label>

                        <x-form-select name="owner_user_id" label="Existing Gym Owner" :selected="$selectedOwnerId">
                            <option value="">Choose an active user</option>
                            @foreach ($ownerCandidates as $ownerCandidate)
                                <option value="{{ $ownerCandidate->id }}" @selected((string) $selectedOwnerId === (string) $ownerCandidate->id)>
                                    {{ $ownerCandidate->name }} • {{ $ownerCandidate->email }}
                                </option>
                            @endforeach
                        </x-form-select>

                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                            <input class="mt-1 h-4 w-4 rounded-full border-slate-300 text-brand-500 focus:ring-brand-500/20" type="radio" name="owner_mode" id="owner_mode_new" value="new" @checked(old('owner_mode', $selectedOwnerId ? 'existing' : 'new') === 'new')>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Create new owner</span>
                                <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">A temporary password will be generated automatically.</span>
                            </span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <x-form-input name="owner_name" label="Owner Name" :value="old('owner_name')" placeholder="Owner full name" />
                            <x-form-input type="email" name="owner_email" label="Owner Email" :value="old('owner_email')" placeholder="owner@example.com" />
                        </div>
                    </div>
                </x-premium-card>
            @else
                <x-premium-card class="overflow-hidden">
                    <div class="border-b border-slate-200/80 px-5 py-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="panel-section-title">Gym Owner</h3>
                                <p class="panel-section-copy">Change the owner safely without leaving this screen.</p>
                            </div>
                            <x-status-badge :label="$gym->owner?->is_active ? 'Owner Active' : 'Owner Missing/Inactive'" />
                        </div>
                    </div>
                    <div class="p-5">
                        <x-form-select name="owner_user_id" label="Owner User" :selected="$selectedOwnerId">
                            <option value="">Choose an active user</option>
                            @foreach ($ownerCandidates as $ownerCandidate)
                                <option value="{{ $ownerCandidate->id }}" @selected((string) $selectedOwnerId === (string) $ownerCandidate->id)>
                                    {{ $ownerCandidate->name }} • {{ $ownerCandidate->email }}
                                </option>
                            @endforeach
                        </x-form-select>
                    </div>
                </x-premium-card>
            @endunless

            <x-premium-card class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Platform Subscription</h3>
                            <p class="panel-section-copy">What this gym pays to the platform for admin services and visibility.</p>
                        </div>
                        @if ($currentPlatformSubscription)
                            <x-status-badge :label="$currentPlatformSubscription->status" />
                        @else
                            <x-status-badge label="Not assigned" tone="neutral" />
                        @endif
                    </div>
                </div>
                <div class="space-y-4 p-5">
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Assign platform subscription in this form</span>
                            <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Use this to create or update the gym's billing setup without leaving the gym flow.</span>
                        </span>
                        <input type="checkbox" name="assign_platform_subscription" value="1" class="form-check-input" @checked($assignPlatformSubscription) data-platform-subscription-toggle>
                    </label>

                    <div class="grid gap-4 md:grid-cols-2" data-platform-subscription-fields>
                        <div class="md:col-span-2">
                            <label class="panel-label" for="platform_subscription_plan_id">Platform Plan</label>
                            <select id="platform_subscription_plan_id" name="platform_subscription_plan_id" class="panel-select">
                                <option value="">Custom billing without template</option>
                                @foreach ($platformPlans as $plan)
                                    <option value="{{ $plan->id }}" @selected((string) old('platform_subscription_plan_id', $currentPlatformSubscription?->platform_subscription_plan_id) === (string) $plan->id)>
                                        {{ $plan->name }} • {{ $plan->price_label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <x-form-select
                            name="platform_subscription_status"
                            label="Subscription Status"
                            :selected="old('platform_subscription_status', $currentPlatformSubscription?->status ?: 'active')"
                            :options="['trialing' => 'Trialing', 'active' => 'Active', 'past_due' => 'Past Due', 'cancelled' => 'Cancelled', 'expired' => 'Expired']"
                        />
                        <label class="flex items-end rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-200">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" name="platform_subscription_auto_renew" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500/20" @checked(old('platform_subscription_auto_renew', $currentPlatformSubscription?->auto_renew ?? true))>
                                Auto renew this gym subscription
                            </span>
                        </label>
                        <x-form-input name="platform_subscription_starts_at" label="Starts At" type="date" :value="old('platform_subscription_starts_at', optional($currentPlatformSubscription?->starts_at)->format('Y-m-d'))" />
                        <x-form-input name="platform_subscription_renews_at" label="Renews At" type="date" :value="old('platform_subscription_renews_at', optional($currentPlatformSubscription?->renews_at)->format('Y-m-d'))" />
                        <x-form-input name="platform_subscription_ends_at" label="Ends At" type="date" :value="old('platform_subscription_ends_at', optional($currentPlatformSubscription?->ends_at)->format('Y-m-d'))" />
                        <x-form-input name="platform_subscription_trial_ends_at" label="Trial Ends At" type="date" :value="old('platform_subscription_trial_ends_at', optional($currentPlatformSubscription?->trial_ends_at)->format('Y-m-d'))" />
                        <x-form-input name="platform_subscription_billing_amount" label="Billing Amount" type="number" step="0.01" :value="old('platform_subscription_billing_amount', $currentPlatformSubscription?->billing_amount)" min="0" />
                        <x-form-input name="platform_subscription_setup_fee_amount" label="Setup Fee Amount" type="number" step="0.01" :value="old('platform_subscription_setup_fee_amount', $currentPlatformSubscription?->setup_fee_amount)" min="0" />
                        <div class="md:col-span-2">
                            <label class="panel-label" for="platform_subscription_included_services_text">Included Services</label>
                            <textarea id="platform_subscription_included_services_text" name="platform_subscription_included_services_text" rows="4" class="panel-textarea" placeholder="One service per line">{{ old('platform_subscription_included_services_text', implode(PHP_EOL, $currentPlatformSubscription?->included_services ?? [])) }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="panel-label" for="platform_subscription_notes">Notes</label>
                            <textarea id="platform_subscription_notes" name="platform_subscription_notes" rows="4" class="panel-textarea" placeholder="Internal billing notes, overrides, or service context.">{{ old('platform_subscription_notes', $currentPlatformSubscription?->notes) }}</textarea>
                        </div>
                    </div>

                    @if ($currentPlatformSubscription)
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">
                            <div class="font-semibold text-slate-950 dark:text-white">{{ $currentPlatformSubscription->plan?->name ?? ($currentPlatformSubscription->plan_snapshot['name'] ?? 'Custom billing') }}</div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $currentPlatformSubscription->plan_snapshot['cadence_label'] ?? $currentPlatformSubscription->plan?->cadence_label ?? 'Custom cadence' }}
                                • Assigned by {{ $currentPlatformSubscription->assignedBy?->name ?? 'System' }}
                            </div>
                        </div>
                    @endif
                </div>
            </x-premium-card>

            <x-premium-card class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Media</h3>
                            <p class="panel-section-copy">Upload optimized branding and gallery media for discovery, listings, and faster previews.</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-4 p-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label for="logo" class="block rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4 transition hover:border-brand-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/70 dark:hover:border-brand-500/40">
                            <span class="mb-3 block text-sm font-semibold text-slate-950 dark:text-white">Logo Upload</span>
                            <span class="mb-4 block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Square brand mark. We compress the file and generate a thumbnail automatically.</span>
                            <span class="flex items-center gap-4">
                                <span data-logo-preview-shell class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-[1.2rem] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                    @if ($gym->logo_thumbnail_url)
                                        <img data-logo-preview src="{{ $gym->logo_thumbnail_url }}" alt="{{ $gym->name }} logo" class="h-full w-full object-cover">
                                    @else
                                        <span data-logo-placeholder class="text-[11px] font-medium uppercase tracking-[0.18em] text-slate-400">No logo</span>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="inline-flex rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">Choose file</span>
                                    <span data-logo-file-name class="mt-2 block text-[11px] font-medium leading-5 text-slate-700 dark:text-slate-200">No file selected</span>
                                    <span class="mt-1 block text-[11px] leading-5 text-slate-500 dark:text-slate-400">JPG, PNG, or WebP up to 4 MB.</span>
                                </span>
                            </span>
                            <input id="logo" type="file" name="logo" class="sr-only" accept=".jpg,.jpeg,.png,.webp">
                        </label>

                        <label for="cover_image" class="block rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4 transition hover:border-brand-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/70 dark:hover:border-brand-500/40">
                            <span class="mb-3 block text-sm font-semibold text-slate-950 dark:text-white">Cover Upload</span>
                            <span class="mb-4 block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Wide listing image for discovery cards and the public profile hero.</span>
                            <span data-cover-preview-shell class="block overflow-hidden rounded-[1.2rem] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                @if ($gym->cover_image_thumbnail_url)
                                    <img data-cover-preview src="{{ $gym->cover_image_thumbnail_url }}" alt="{{ $gym->name }} cover" class="h-28 w-full object-cover">
                                @else
                                    <span data-cover-placeholder class="flex h-28 items-center justify-center text-[11px] font-medium uppercase tracking-[0.18em] text-slate-400">No cover image</span>
                                @endif
                            </span>
                            <span class="mt-3 inline-flex rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">Select cover</span>
                            <span data-cover-file-name class="mt-2 block text-[11px] font-medium leading-5 text-slate-700 dark:text-slate-200">No file selected</span>
                            <input id="cover_image" type="file" name="cover_image" class="sr-only" accept=".jpg,.jpeg,.png,.webp">
                        </label>
                    </div>
                    @error('logo')
                        <p class="text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                    @error('cover_image')
                        <p class="text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror

                    <div class="rounded-[1.4rem] border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <label for="gallery_images" class="panel-label !mb-1">Gallery Photos</label>
                                <p class="text-[11px] leading-5 text-slate-500 dark:text-slate-400">Upload up to 10 discovery images. Each upload is compressed and a thumbnail is created for faster list rendering.</p>
                            </div>
                            <x-status-badge :label="$galleryPreviewPhotos->count().' saved'" tone="info" />
                        </div>

                        <input id="gallery_images" type="file" name="gallery_images[]" class="panel-input mt-4" accept=".jpg,.jpeg,.png,.webp" multiple>
                        <p data-gallery-file-name class="mt-2 text-[11px] font-medium leading-5 text-slate-700 dark:text-slate-200">No new files selected</p>
                        @error('gallery_images')
                            <p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                        @error('remove_gallery_photo_ids')
                            <p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror

                        @if ($galleryPreviewPhotos->isNotEmpty())
                            <div class="mt-4 rounded-[1.2rem] border border-slate-200 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-900/50">
                                <div class="mb-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Current Gallery</div>
                                <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
                                    @foreach ($galleryPreviewPhotos as $photo)
                                        <label class="overflow-hidden rounded-[1.1rem] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                            <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery photo" class="h-24 w-full object-cover">
                                            <span class="flex items-center gap-2 px-3 py-3 text-xs font-medium text-slate-700 dark:text-slate-200">
                                                <input type="checkbox" name="remove_gallery_photo_ids[]" value="{{ $photo->id }}" class="h-4 w-4 rounded border-slate-300" @checked(in_array($photo->id, $removedGalleryPhotoIds, true))>
                                                Remove this photo
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div data-gallery-preview-grid class="mt-4 grid grid-cols-2 gap-3 xl:grid-cols-3 hidden"></div>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5">
                    <h3 class="panel-section-title">Discovery Settings</h3>
                    <p class="panel-section-copy">Keep visibility and lead controls tight.</p>
                </div>
                <div class="space-y-3 p-5">
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Public Listing Enabled</span>
                            <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Allow the gym to appear in discovery.</span>
                        </span>
                        <input type="checkbox" name="public_listing_enabled" value="1" class="form-check-input" @checked(old('public_listing_enabled', $gym->public_listing_enabled))>
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Show Pricing</span>
                            <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Expose pricing on the public profile.</span>
                        </span>
                        <input type="checkbox" name="show_pricing" value="1" class="form-check-input" @checked(old('show_pricing', $gym->show_pricing ?? $gym->pricing_visible ?? true))>
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Trial Available</span>
                            <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Mark whether a free trial can be booked.</span>
                        </span>
                        <input type="checkbox" name="trial_available" value="1" class="form-check-input" @checked(old('trial_available', $gym->trial_available))>
                    </label>
                    <label class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/70">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">Contact Visible</span>
                            <span class="block text-[11px] leading-5 text-slate-500 dark:text-slate-400">Show the gym phone and contact points.</span>
                        </span>
                        <input type="checkbox" name="contact_visible" value="1" class="form-check-input" @checked(old('contact_visible', $gym->contact_visible ?? true))>
                    </label>
                </div>
            </x-premium-card>

            <x-premium-card class="overflow-hidden">
                <div class="border-b border-slate-200/80 px-5 py-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="panel-section-title">Facilities</h3>
                            <p class="panel-section-copy">Attach platform-managed facilities to the gym.</p>
                        </div>
                        <x-status-badge :label="count($selectedFacilityIds).' selected'" tone="info" />
                    </div>
                </div>
                <div class="p-5">
                    @if ($facilities->isEmpty())
                        <x-empty-state title="No facilities available" message="Create facilities from Platform Admin before assigning them to gyms." />
                    @else
                        <label for="facility_ids" class="panel-label">Facility Multi-Select</label>
                        <select id="facility_ids" name="facility_ids[]" class="panel-select" multiple size="10">
                            @foreach ($facilities as $facility)
                                <option value="{{ $facility->id }}" @selected(in_array((int) $facility->id, $selectedFacilityIds, true))>
                                    {{ $facility->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-[11px] leading-5 text-slate-500">Hold command or control to select multiple facilities.</p>
                    @endif
                </div>
            </x-premium-card>
        </div>
    </div>
</form>

@once
    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('gym-form');

                if (!form) {
                    return;
                }

                const createBranchToggle = form.querySelector('#create_default_branch');
                const sameAsGymToggle = form.querySelector('#branch_same_as_gym');
                const platformSubscriptionToggle = form.querySelector('[data-platform-subscription-toggle]');
                const platformSubscriptionFields = form.querySelector('[data-platform-subscription-fields]');
                const branchNameInput = form.querySelector('[name="branch_name"]');
                const branchFields = Array.from(form.querySelectorAll('[name^="branch_"]'));
                const branchScheduleShell = sameAsGymToggle?.closest('.grid')?.querySelector('[data-operating-hours-editor]')?.closest('.md\\:col-span-2');
                const logoInput = form.querySelector('#logo');
                const logoFileName = form.querySelector('[data-logo-file-name]');
                const logoPreviewShell = form.querySelector('[data-logo-preview-shell]');
                const logoPreview = form.querySelector('[data-logo-preview]');
                const logoPlaceholder = form.querySelector('[data-logo-placeholder]');
                const coverInput = form.querySelector('#cover_image');
                const coverFileName = form.querySelector('[data-cover-file-name]');
                const coverPreviewShell = form.querySelector('[data-cover-preview-shell]');
                const coverPreview = form.querySelector('[data-cover-preview]');
                const coverPlaceholder = form.querySelector('[data-cover-placeholder]');
                const galleryInput = form.querySelector('#gallery_images');
                const galleryFileName = form.querySelector('[data-gallery-file-name]');
                const galleryGrid = form.querySelector('[data-gallery-preview-grid]');

                const syncBranchState = () => {
                    const branchEnabled = createBranchToggle ? createBranchToggle.checked : true;
                    const branchIndependent = sameAsGymToggle ? !sameAsGymToggle.checked : true;

                    branchFields.forEach((field) => {
                        if (field === createBranchToggle || field === sameAsGymToggle) {
                            return;
                        }

                        if (field === branchNameInput) {
                            field.disabled = !branchEnabled;
                            return;
                        }

                        field.disabled = !branchEnabled || !branchIndependent;
                    });

                    if (branchScheduleShell) {
                        branchScheduleShell.classList.toggle('opacity-50', !branchEnabled || !branchIndependent);
                        branchScheduleShell.classList.toggle('pointer-events-none', !branchEnabled || !branchIndependent);
                    }
                };

                createBranchToggle?.addEventListener('change', syncBranchState);
                sameAsGymToggle?.addEventListener('change', syncBranchState);
                syncBranchState();

                const syncPlatformSubscriptionState = () => {
                    if (!platformSubscriptionToggle || !platformSubscriptionFields) {
                        return;
                    }

                    const enabled = platformSubscriptionToggle.checked;
                    const fields = platformSubscriptionFields.querySelectorAll('input, select, textarea');

                    fields.forEach((field) => {
                        field.disabled = !enabled;
                    });

                    platformSubscriptionFields.classList.toggle('opacity-50', !enabled);
                    platformSubscriptionFields.classList.toggle('pointer-events-none', !enabled);
                };

                platformSubscriptionToggle?.addEventListener('change', syncPlatformSubscriptionState);
                syncPlatformSubscriptionState();

                const readImagePreview = (input, onLoad) => {
                    const [file] = input.files || [];

                    if (!file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (event) => {
                        if (typeof event.target?.result === 'string') {
                            onLoad(event.target.result, file);
                        }
                    };
                    reader.readAsDataURL(file);
                };

                logoInput?.addEventListener('change', () => {
                    const [file] = logoInput.files || [];
                    if (logoFileName) {
                        logoFileName.textContent = file ? file.name : 'No file selected';
                    }

                    if (!file || !logoPreviewShell) {
                        return;
                    }

                    readImagePreview(logoInput, (src) => {
                        if (logoPlaceholder) {
                            logoPlaceholder.remove();
                        }

                        let image = logoPreviewShell.querySelector('img');
                        if (!image) {
                            image = document.createElement('img');
                            image.className = 'h-full w-full object-cover';
                            image.setAttribute('data-logo-preview', '');
                            logoPreviewShell.innerHTML = '';
                            logoPreviewShell.appendChild(image);
                        }

                        image.src = src;
                    });
                });

                coverInput?.addEventListener('change', () => {
                    const [file] = coverInput.files || [];
                    if (coverFileName) {
                        coverFileName.textContent = file ? file.name : 'No file selected';
                    }

                    if (!file || !coverPreviewShell) {
                        return;
                    }

                    readImagePreview(coverInput, (src) => {
                        if (coverPlaceholder) {
                            coverPlaceholder.remove();
                        }

                        let image = coverPreviewShell.querySelector('img');
                        if (!image) {
                            image = document.createElement('img');
                            image.className = 'h-28 w-full object-cover';
                            image.setAttribute('data-cover-preview', '');
                            coverPreviewShell.innerHTML = '';
                            coverPreviewShell.appendChild(image);
                        }

                        image.src = src;
                    });
                });

                galleryInput?.addEventListener('change', () => {
                    const files = Array.from(galleryInput.files || []);

                    if (galleryFileName) {
                        galleryFileName.textContent = files.length
                            ? `${files.length} file${files.length === 1 ? '' : 's'} selected`
                            : 'No new files selected';
                    }

                    if (!galleryGrid) {
                        return;
                    }

                    if (!files.length) {
                        return;
                    }

                    galleryGrid.innerHTML = '';
                    galleryGrid.classList.remove('hidden');

                    files.slice(0, 6).forEach((file) => {
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            if (typeof event.target?.result !== 'string') {
                                return;
                            }

                            const card = document.createElement('div');
                            card.className = 'overflow-hidden rounded-[1.1rem] border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';

                            const image = document.createElement('img');
                            image.src = event.target.result;
                            image.alt = file.name;
                            image.className = 'h-24 w-full object-cover';

                            card.appendChild(image);
                            galleryGrid.appendChild(card);
                        };
                        reader.readAsDataURL(file);
                    });
                });
            })();
        </script>
    @endpush
@endonce
