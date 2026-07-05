@php
    use App\Support\Scheduling\OperatingHours;

    $branch = $branch ?? null;
    $branchTimingsValue = old('timings_json')
        ? json_decode((string) old('timings_json'), true)
        : OperatingHours::normalize($branch?->timings ?? [], $branch?->weekly_off ?? []);
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <x-form-input name="name" label="Branch Name" :value="$branch?->name" required />
    <x-form-input name="slug" label="Slug" :value="$branch?->slug" placeholder="Optional auto-generated" />

    <x-form-input name="address" label="Address" :value="$branch?->address ?: $branch?->address_line" />
    <div>
        <label for="city_id" class="panel-label">Linked City</label>
        <select id="city_id" name="city_id" class="panel-select">
            <option value="">No linked city record</option>
            @foreach ($cities as $city)
                <option value="{{ $city->id }}" @selected((int) old('city_id', $branch?->city_id) === $city->id)>{{ $city->name }}</option>
            @endforeach
        </select>
    </div>

    <x-form-input name="city" label="Display City" :value="$branch?->city" placeholder="Shown in listings and branch cards" />
    <x-form-input name="state" label="State" :value="$branch?->state" />

    <x-form-input name="country" label="Country" :value="$branch?->country ?: 'India'" />
    <x-form-input name="pincode" label="Pincode" :value="$branch?->pincode" />

    <x-form-input name="timezone" label="Timezone" :value="$branch?->timezone ?: ($gym->timezone ?? config('app.timezone'))" />
    <x-form-input name="latitude" label="Latitude" :value="$branch?->latitude" />

    <x-form-input name="longitude" label="Longitude" :value="$branch?->longitude" />

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
        <label for="photo_urls_text" class="panel-label">Photo URLs</label>
        <textarea id="photo_urls_text" name="photo_urls_text" class="panel-textarea" rows="3" placeholder="One image URL per line">{{ old('photo_urls_text', $branch ? implode("\n", $branch->photo_urls ?? []) : '') }}</textarea>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Up to 10 URLs. These support branch discovery, listing media, and gallery previews.</p>
    </div>

    <div class="md:col-span-2">
        <label for="facility_ids" class="panel-label">Facilities</label>
        <select id="facility_ids" name="facility_ids[]" class="panel-select min-h-40" multiple>
            @foreach ($facilities as $facility)
                <option value="{{ $facility->id }}" @selected(in_array($facility->id, old('facility_ids', $branch?->facilities?->pluck('id')->all() ?? []), true))>
                    {{ $facility->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true)) class="h-4 w-4 rounded border-white/20 bg-slate-950/60 text-sky-400">
            Branch is active
        </label>
    </div>
</div>
