@php
    $selectedFacilityIds = collect(old('facility_ids', ($selectedFacilities ?? $gym->facilities)->pluck('id')->all()))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div data-facility-picker class="space-y-3">
    <input type="hidden" name="facility_ids_present" value="1">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h4 class="text-sm font-semibold text-slate-950 dark:text-slate-100">Facilities</h4>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $facilityDescription ?? 'Choose the amenities available at this gym.' }}</p>
        </div>
        <span data-facility-count class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ count($selectedFacilityIds) }} selected</span>
    </div>

    <div data-facility-chips class="flex flex-wrap gap-2" aria-live="polite">
        @foreach ($facilities->whereIn('id', $selectedFacilityIds) as $facility)
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $facility->name }}</span>
        @endforeach
    </div>
    <p data-facility-empty class="text-sm text-slate-600 dark:text-slate-400 {{ $selectedFacilityIds ? 'hidden' : '' }}">No facilities selected yet.</p>

    <details class="group rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-brand-300">
            <span><i class="ti ti-adjustments-horizontal mr-2" aria-hidden="true"></i>Choose facilities</span>
            <i class="ti ti-chevron-down transition group-open:rotate-180" aria-hidden="true"></i>
        </summary>
        <div class="border-t border-slate-200 p-4 dark:border-slate-700">
            <label for="facility-search" class="panel-label">Search facilities</label>
            <input id="facility-search" data-facility-search type="search" class="panel-input" placeholder="Search by name" autocomplete="off">
            <div class="mt-4 grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2" data-facility-options>
                @foreach ($facilities as $facility)
                    <label data-facility-option data-facility-name="{{ $facility->name }}" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">
                        <input type="checkbox" name="facility_ids[]" value="{{ $facility->id }}" class="h-5 w-5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900" @checked(in_array((int) $facility->id, $selectedFacilityIds, true))>
                        <span class="min-w-0 break-words">{{ $facility->name }}</span>
                    </label>
                @endforeach
            </div>
            <p data-facility-no-results class="mt-3 hidden text-sm text-slate-600 dark:text-slate-400">{{ $facilities->isEmpty() ? 'No facilities are available yet.' : 'No matching facilities. Try another search.' }}</p>
            <button type="button" data-facility-more class="mt-3 hidden min-h-11 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Load more facilities</button>
        </div>
    </details>
    @error('facility_ids')<p class="text-sm text-error-600 dark:text-error-300">{{ $message }}</p>@enderror
    @if ($errors->has('facility_ids.*'))<p class="text-sm text-error-600 dark:text-error-300">{{ $errors->first('facility_ids.*') }}</p>@endif
</div>
