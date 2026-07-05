@extends('layouts.panel')

@php
    use App\Support\Scheduling\OperatingHours;

    $isPubliclyEligible = $gym->is_active
        && ($gym->status === 'active')
        && (($gym->approval_status ?? null) === 'approved' || ($gym->approval_status ?? null) === null);
    $gymTimingsValue = old('timings_json')
        ? json_decode((string) old('timings_json'), true)
        : OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $todayKey = strtolower(now($gym->timezone ?: config('app.timezone'))->englishDayOfWeek);
    $galleryPhotos = $gym->gymPhotos->whereNull('branch_id')->where('type', 'gallery')->sortBy('sort_order')->values();
    $removedGalleryPhotoIds = collect(old('remove_gallery_photo_ids', []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-4">
            <x-stat-card label="Facilities" :value="$gym->facilities->count()" hint="Assigned to this gym" tone="sky" />
            <x-stat-card label="Gallery" :value="$galleryPhotos->count()" hint="Public discovery images" tone="violet" />
            <x-stat-card label="Public Listing" :value="$gym->public_listing_enabled ? 'Enabled' : 'Private'" hint="Discovery visibility" tone="emerald" />
            <x-stat-card label="Pricing" :value="($gym->show_pricing ?? $gym->pricing_visible) ? 'Visible' : 'Hidden'" hint="Public pricing display" tone="amber" />
        </div>

        @if (! $isPubliclyEligible)
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                <p class="font-semibold text-amber-900">Public listing is restricted</p>
                <p class="mt-1">This gym is currently <strong>{{ ucfirst($gym->status ?: 'inactive') }}</strong>. Discovery will stay private until the gym is active and platform-approved.</p>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <x-premium-card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-700">Gym identity</p>
                        <h3 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950">Edit Gym Profile</h3>
                        <p class="mt-2 text-sm text-slate-500">Update your branding, timings, location, facilities, and gallery for both operations and public discovery.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-status-badge :label="ucfirst($gym->status ?: 'inactive')" />
                        <x-status-badge :label="$gym->public_listing_enabled ? 'Public' : 'Private'" />
                        <x-status-badge :label="$gym->contact_visible ? 'Contact Visible' : 'Contact Hidden'" />
                    </div>
                </div>

                <form action="{{ route('web.gym.profile.update') }}" method="POST" enctype="multipart/form-data" class="mt-6 grid gap-5 md:grid-cols-2">
                    @csrf
                    @method('PUT')

                    <x-form-input name="name" label="Gym Name" :value="$gym->name" required />
                    <x-form-input name="city" label="City" :value="$gym->city" required />

                    <div class="md:col-span-2">
                        <label for="description" class="panel-label">Description</label>
                        <textarea id="description" name="description" class="panel-textarea" rows="4">{{ old('description', $gym->description) }}</textarea>
                    </div>

                    <div>
                        <label for="logo" class="panel-label">Logo Upload</label>
                        <input id="logo" name="logo" type="file" accept="image/*" class="panel-input">
                        @error('logo')
                            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @if ($gym->logo_url)
                            <label class="mt-3 flex items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="remove_logo" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('remove_logo'))>
                                Remove current logo
                            </label>
                        @endif
                    </div>
                    <div>
                        <label for="cover_image" class="panel-label">Cover Image Upload</label>
                        <input id="cover_image" name="cover_image" type="file" accept="image/*" class="panel-input">
                        @error('cover_image')
                            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @if ($gym->cover_image_url)
                            <label class="mt-3 flex items-center gap-3 text-sm text-slate-600 dark:text-slate-300">
                                <input type="checkbox" name="remove_cover_image" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('remove_cover_image'))>
                                Remove current cover image
                            </label>
                        @endif
                    </div>

                    <x-form-input name="logo_url" label="Fallback Logo URL" :value="$gym->logo_url" />
                    <x-form-input name="cover_image_url" label="Fallback Cover URL" :value="$gym->cover_image_url" />

                    <x-form-input name="address" label="Address" :value="$gym->address ?: $gym->address_line" />
                    <x-form-input name="state" label="State" :value="$gym->state" />
                    <x-form-input name="contact_number" label="Contact Number" :value="$gym->contact_number" />
                    <x-form-input name="instagram_profile" label="Instagram Profile" :value="$gym->instagram_profile" />
                    <x-form-input name="country" label="Country" :value="$gym->country ?: 'India'" />
                    <x-form-input name="pincode" label="Pincode" :value="$gym->pincode" />
                    <x-form-input name="latitude" label="Latitude" :value="$gym->latitude" />
                    <x-form-input name="longitude" label="Longitude" :value="$gym->longitude" />
                    <x-form-input name="timezone" label="Timezone" :value="$gym->timezone" />

                    <div class="md:col-span-2">
                        <x-admin.operating-hours-editor
                            id="gym_profile_timings_json"
                            name="timings_json"
                            label="Operating Schedule"
                            :value="$gymTimingsValue"
                            helper="Set exact branch-ready hours for each day, including multiple sessions like morning and evening."
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label for="facility_ids" class="panel-label">Facilities</label>
                        <select id="facility_ids" name="facility_ids[]" class="panel-select min-h-40" multiple>
                            @foreach ($facilities as $facility)
                                <option value="{{ $facility->id }}" @selected(in_array($facility->id, old('facility_ids', $gym->facilities->pluck('id')->all()), true))>
                                    {{ $facility->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="gallery_images" class="panel-label">Upload Gallery Photos</label>
                        <input id="gallery_images" name="gallery_images[]" type="file" accept="image/*" multiple class="panel-input">
                        @error('gallery_images')
                            <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($galleryPhotos->isNotEmpty())
                        <div class="md:col-span-2 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/60">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">Current Gallery</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose any photo you want to remove. Uploading new photos adds them to the remaining gallery.</p>
                                </div>
                                <x-status-badge :label="$galleryPhotos->count().' saved'" />
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($galleryPhotos as $photo)
                                    <label class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                        <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery photo" class="h-32 w-full object-cover">
                                        <span class="flex items-center gap-3 px-3 py-3 text-sm text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" name="remove_gallery_photo_ids[]" value="{{ $photo->id }}" class="h-4 w-4 rounded border-slate-300" @checked(in_array($photo->id, $removedGalleryPhotoIds, true))>
                                            Remove this photo
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="md:col-span-2 grid gap-4 rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700 md:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <input type="hidden" name="public_listing_enabled" value="0">
                            <input type="checkbox" name="public_listing_enabled" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($gym->public_listing_enabled)>
                            <span><span class="font-semibold text-slate-950">Enable public listing</span><br>Visible in discovery only when this gym is active and approved.</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <input type="hidden" name="show_pricing" value="0">
                            <input type="checkbox" name="show_pricing" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($gym->show_pricing ?? $gym->pricing_visible)>
                            <span><span class="font-semibold text-slate-950">Show pricing publicly</span><br>Expose membership plan pricing on the public profile.</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <input type="hidden" name="trial_available" value="0">
                            <input type="checkbox" name="trial_available" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($gym->trial_available)>
                            <span><span class="font-semibold text-slate-950">Accept trial requests</span><br>Allow nearby users to request a trial from discovery.</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <input type="hidden" name="contact_visible" value="0">
                            <input type="checkbox" name="contact_visible" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($gym->contact_visible)>
                            <span><span class="font-semibold text-slate-950">Allow public contact CTA</span><br>Enable the public trial/contact action on the discovery profile.</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <x-action-button type="submit" variant="primary" class="w-full justify-center">Save Gym Profile</x-action-button>
                    </div>
                </form>
            </x-premium-card>

            <div class="space-y-6">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Media Preview</h3>
                    <div class="mt-4 space-y-4">
                        <div class="overflow-hidden rounded-3xl border border-white/10 bg-slate-950/50">
                            @if ($gym->cover_image_url)
                                <img src="{{ $gym->cover_image_thumbnail_url ?: $gym->cover_image_url }}" alt="{{ $gym->name }} cover" class="h-44 w-full object-cover">
                            @else
                                <div class="flex h-44 items-center justify-center text-sm text-slate-400">No cover image yet</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-4 rounded-3xl border border-white/10 bg-white/[0.03] p-4">
                            <div class="h-20 w-20 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/50">
                                @if ($gym->logo_url)
                                    <img src="{{ $gym->logo_thumbnail_url ?: $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full items-center justify-center text-[11px] text-slate-400">No logo</div>
                                @endif
                            </div>
                            <div>
                                <p class="text-lg font-semibold text-slate-950">{{ $gym->name }}</p>
                                <p class="text-sm text-slate-500">{{ $gym->city ?: 'City pending' }}{{ $gym->state ? ', '.$gym->state : '' }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-status-badge :label="$gym->is_verified ? 'Verified' : 'Unverified'" />
                                    <x-status-badge :label="$gym->public_listing_enabled ? 'Public' : 'Private'" />
                                </div>
                            </div>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Public Discovery Preview</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="panel-card-muted px-4 py-3">Address: {{ $gym->address ?: $gym->address_line ?: 'Not configured' }}</div>
                        <div class="panel-card-muted px-4 py-3">Contact number: {{ $gym->contact_number ?: 'Not configured' }}</div>
                        <div class="panel-card-muted px-4 py-3">Instagram: {{ $gym->instagram_profile ?: 'Not configured' }}</div>
                        <div class="panel-card-muted px-4 py-3">Facilities: {{ $gym->facilities->pluck('name')->implode(', ') ?: 'No facilities selected yet' }}</div>
                        <div class="panel-card-muted px-4 py-3">Public pricing: {{ ($gym->show_pricing ?? $gym->pricing_visible) ? 'Visible' : 'Hidden' }}</div>
                        <div class="panel-card-muted px-4 py-3">Contact CTA: {{ $gym->contact_visible ? 'Enabled' : 'Hidden' }}</div>
                        <div class="panel-card-muted px-4 py-3">
                            Schedule preview: {{ OperatingHours::dayLabel($todayKey) }} • {{ OperatingHours::formatDaySlots($gymTimingsValue[$todayKey] ?? []) }}
                        </div>
                    </div>
                    @if ($galleryPhotos->isNotEmpty())
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            @foreach ($galleryPhotos->take(4) as $photo)
                                <div class="overflow-hidden rounded-2xl border border-white/10 bg-slate-950/50">
                                    <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery photo" class="h-28 w-full object-cover">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
