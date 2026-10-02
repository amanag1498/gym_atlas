@extends('layouts.panel')

@php
    use App\Support\Scheduling\OperatingHours;

    $panelFullWidth = true;

    $isPubliclyEligible = $gym->is_active
        && $gym->status === 'active'
        && (($gym->approval_status ?? null) === 'approved' || ($gym->approval_status ?? null) === null);
    $gymTimingsValue = old('timings_json')
        ? json_decode((string) old('timings_json'), true)
        : OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $galleryPhotos = $gym->gymPhotos->whereNull('branch_id')->where('type', 'gallery')->sortBy('sort_order')->values();
    $removedGalleryPhotoIds = collect(old('remove_gallery_photo_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Gym profile</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Keep your details, hours, and photos up to date.</p>
            </div>
            <x-action-button as="a" variant="secondary" href="{{ route('web.gym.public-listing.edit', request()->only(['gym', 'branch'])) }}">Public listing settings</x-action-button>
        </header>

        @if (! $isPubliclyEligible)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200" role="status">
                <p class="font-semibold">Public listing is currently unavailable</p>
                <p class="mt-1">Your gym must be active and approved before it can appear in discovery. You can still update the profile below.</p>
            </div>
        @endif

        <form action="{{ route('web.gym.profile.update', request()->only(['gym', 'branch'])) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="profile-identity-heading">
                <div class="border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h3 id="profile-identity-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Identity & media</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">These details introduce your gym to members.</p>
                </div>
                <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(15rem,0.7fr)]">
                    <div class="space-y-5">
                        <x-form-input name="name" label="Gym name" :value="$gym->name" required />
                        <div>
                            <label for="description" class="panel-label">Description</label>
                            <textarea id="description" name="description" class="panel-textarea" rows="5" placeholder="What makes this gym a great place to train?">{{ old('description', $gym->description) }}</textarea>
                            @error('description')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="logo" class="panel-label">Logo</label>
                                <input id="logo" name="logo" type="file" accept="image/*" class="panel-input">
                                @error('logo')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
                                @if ($gym->logo_url)
                                    <label class="mt-3 flex min-h-11 items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" name="remove_logo" value="1" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('remove_logo'))>
                                        Remove current logo
                                    </label>
                                @endif
                            </div>
                            <div>
                                <label for="cover_image" class="panel-label">Cover image</label>
                                <input id="cover_image" name="cover_image" type="file" accept="image/*" class="panel-input">
                                @error('cover_image')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
                                @if ($gym->cover_image_url)
                                    <label class="mt-3 flex min-h-11 items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" name="remove_cover_image" value="1" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(old('remove_cover_image'))>
                                        Remove current cover image
                                    </label>
                                @endif
                            </div>
                        </div>
                        <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">Use image URLs instead</summary>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <x-form-input name="logo_url" label="Logo URL" :value="$gym->logo_url" />
                                <x-form-input name="cover_image_url" label="Cover image URL" :value="$gym->cover_image_url" />
                            </div>
                        </details>
                    </div>
                    <div class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                        @if ($gym->cover_image_url)
                            <img src="{{ $gym->cover_image_thumbnail_url ?: $gym->cover_image_url }}" alt="{{ $gym->name }} cover" class="h-36 w-full object-cover">
                        @else
                            <div class="flex h-36 items-center justify-center text-sm text-slate-600 dark:text-slate-400">No cover image yet</div>
                        @endif
                        <div class="flex min-w-0 items-center gap-3 p-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                                @if ($gym->logo_url)
                                    <img src="{{ $gym->logo_thumbnail_url ?: $gym->logo_url }}" alt="{{ $gym->name }} logo" class="h-full w-full object-cover">
                                @else
                                    <span class="text-xs text-slate-600 dark:text-slate-400">No logo</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-slate-950 dark:text-white">{{ $gym->name }}</p>
                                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $gym->city ?: 'City not set' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="profile-location-heading">
                <div class="border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h3 id="profile-location-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Location & contact</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Help members find and contact your gym.</p>
                </div>
                <div class="mt-5 space-y-5">
                    <x-admin.location-picker
                        id="gym_profile_location"
                        :address-value="$gym->address ?: $gym->address_line"
                        :latitude-value="$gym->latitude"
                        :longitude-value="$gym->longitude"
                        city-name="city"
                        :city-value="$gym->city"
                        :city-required="true"
                        state-name="state"
                        :state-value="$gym->state"
                        pincode-name="pincode"
                        :pincode-value="$gym->pincode"
                        country-name="country"
                        :country-value="$gym->country ?: 'India'"
                    />
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form-input name="contact_number" label="Contact number" :value="$gym->contact_number" />
                        <x-form-input name="instagram_profile" label="Instagram profile" :value="$gym->instagram_profile" />
                        <x-form-input name="timezone" label="Timezone" :value="$gym->timezone" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="profile-hours-heading">
                <div class="border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h3 id="profile-hours-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Opening hours</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Set the schedule members should see.</p>
                </div>
                <div class="mt-5">
                    <x-admin.operating-hours-editor
                        id="gym_profile_timings_json"
                        name="timings_json"
                        label="Operating schedule"
                        :value="$gymTimingsValue"
                        helper="Add morning and evening sessions separately when needed."
                    />
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="profile-facilities-heading">
                <div class="border-b border-slate-200 pb-4 dark:border-slate-800">
                    <h3 id="profile-facilities-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Facilities & gallery</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Show what is available and give visitors a look inside.</p>
                </div>
                <div class="mt-5 space-y-7">
                    @include('web.gym.profile._facility-picker', ['gym' => $gym, 'facilities' => $facilities])
                    <div class="border-t border-slate-200 pt-6 dark:border-slate-800">
                        <label for="gallery_images" class="panel-label">Add gallery photos</label>
                        <input id="gallery_images" name="gallery_images[]" type="file" accept="image/*" multiple class="panel-input">
                        @error('gallery_images')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
                        @if ($galleryPhotos->isNotEmpty())
                            <div class="mt-5">
                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Current gallery · {{ $galleryPhotos->count() }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Select a photo to remove it when you save.</p>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach ($galleryPhotos as $photo)
                                        <label class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                                            <img src="{{ $photo->thumbnail_url }}" alt="Gym gallery photo" class="h-32 w-full object-cover">
                                            <span class="flex min-h-12 items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                                                <input type="checkbox" name="remove_gallery_photo_ids[]" value="{{ $photo->id }}" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(in_array($photo->id, $removedGalleryPhotoIds, true))>
                                                Remove photo
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="space-y-4" aria-labelledby="profile-listing-heading">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="profile-listing-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Public listing options</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Control how this profile appears in discovery.</p>
                    </div>
                    <a href="{{ route('web.gym.public-listing.edit', request()->only(['gym', 'branch'])) }}" class="text-sm font-semibold text-brand-700 hover:underline dark:text-brand-300">View full listing preview</a>
                </div>
                @include('web.gym.public-listing._settings-toggles', ['gym' => $gym])
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800">
                <p class="text-sm text-slate-600 dark:text-slate-400">Changes are saved when you press the button.</p>
                <x-action-button type="submit" variant="primary" class="w-full justify-center sm:w-auto">Save gym profile</x-action-button>
            </div>
        </form>
    </div>
@endsection
