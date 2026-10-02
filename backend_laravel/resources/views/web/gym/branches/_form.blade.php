@php
    use App\Support\Scheduling\OperatingHours;

    $branch = $branch ?? null;
    $branchTimingsValue = old('timings_json')
        ? json_decode((string) old('timings_json'), true)
        : OperatingHours::normalize($branch?->timings ?? [], $branch?->weekly_off ?? []);
    $branchPhotoUrls = collect($branch?->photo_urls ?? [])->values();
    $removedPhotoIndexes = collect(old('remove_photo_indexes', []))->map(fn ($index) => (int) $index)->all();
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <x-form-input name="name" label="Branch Name" :value="$branch?->name" required />
    <x-form-input name="slug" label="Slug" :value="$branch?->slug" placeholder="Optional auto-generated" />

    <div>
        <label for="city_id" class="panel-label">Linked City</label>
        <select id="city_id" name="city_id" class="panel-select">
            <option value="">No linked city record</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected((int) old('city_id', $branch?->city_id) === $city->id)>{{ $city->name }}</option>
            @endforeach
        </select>
    </div>

    <x-admin.location-picker
        id="branch_location"
        class="md:col-span-2"
        :address-value="$branch?->address ?: $branch?->address_line"
        :latitude-value="$branch?->latitude"
        :longitude-value="$branch?->longitude"
        city-name="city"
        :city-value="$branch?->city"
        state-name="state"
        :state-value="$branch?->state"
        pincode-name="pincode"
        :pincode-value="$branch?->pincode"
        country-name="country"
        :country-value="$branch?->country ?: 'India'"
    />

    <x-form-input name="timezone" label="Timezone" :value="$branch?->timezone ?: ($gym->timezone ?? config('app.timezone'))" />

    <div class="md:col-span-2">
        <x-admin.operating-hours-editor
            id="branch_timings_json"
            name="timings_json"
            label="Branch Schedule"
            :value="$branchTimingsValue"
            helper="Configure different operating windows per day, including split morning and evening shifts."
        />
    </div>

    <div class="md:col-span-2">
        <label for="gallery_images" class="panel-label">Branch photos</label>
        <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">Upload up to 10 photos for branch discovery and gallery previews.</p>
        <input id="gallery_images" name="gallery_images[]" type="file" accept=".jpg,.jpeg,.png,.webp" multiple class="panel-input">
        @error('gallery_images')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
        @if ($errors->has('gallery_images.*'))<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $errors->first('gallery_images.*') }}</p>@endif
        @error('remove_photo_indexes')<p class="mt-2 text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
        @if ($branchPhotoUrls->isNotEmpty())
            <p class="mt-5 text-sm font-semibold text-slate-950 dark:text-white">Current photos · {{ $branchPhotoUrls->count() }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Select photos to remove when you save.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($branchPhotoUrls as $index => $photoUrl)
                    <label class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                        <img src="{{ \App\Support\Media\StoredImage::thumbnailUrl($photoUrl, $photoUrl) }}" alt="Branch photo {{ $index + 1 }}" class="h-32 w-full object-cover">
                        <span class="flex min-h-12 items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="remove_photo_indexes[]" value="{{ $index }}" class="h-5 w-5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" @checked(in_array($index, $removedPhotoIndexes, true))>
                            Remove photo
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <div class="md:col-span-2">
        @include('web.gym.profile._facility-picker', ['selectedFacilities' => $branch?->facilities ?? collect(), 'facilities' => $facilities, 'facilityDescription' => 'Choose the amenities available at this branch.'])
    </div>

    <div class="md:col-span-2">
        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true)) class="h-4 w-4 rounded border-white/20 bg-slate-950/60 text-sky-400">
            Branch is active
        </label>
    </div>
</div>
