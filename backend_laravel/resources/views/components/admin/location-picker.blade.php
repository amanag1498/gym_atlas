@props([
    'id',
    'label' => 'Address',
    'addressName' => 'address',
    'addressValue' => null,
    'addressMax' => 255,
    'latitudeName' => 'latitude',
    'latitudeValue' => null,
    'longitudeName' => 'longitude',
    'longitudeValue' => null,
    'cityName' => null,
    'cityValue' => null,
    'cityRequired' => false,
    'stateName' => null,
    'stateValue' => null,
    'pincodeName' => null,
    'pincodeValue' => null,
    'countryName' => null,
    'countryValue' => null,
    'preset' => null,
    'locationNameTarget' => null,
])

@php
    $latitude = old($latitudeName, $latitudeValue);
    $longitude = old($longitudeName, $longitudeValue);
    $hasCoordinates = filled($latitude) && filled($longitude);
@endphp

<div
    {{ $attributes->class(['atlas-location-picker space-y-4 text-slate-900 dark:text-slate-100']) }}
    data-location-picker
    data-picker-id="{{ $id }}"
    data-initial-latitude="{{ $latitude }}"
    data-initial-longitude="{{ $longitude }}"
    data-address-max="{{ $addressMax }}"
    @if($preset) data-location-preset="{{ json_encode($preset) }}" @endif
    @if($locationNameTarget) data-location-name-target="{{ $locationNameTarget }}" @endif
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label for="{{ $id }}_address" class="panel-label">{{ $label }}</label>
            <input
                id="{{ $id }}_address"
                name="{{ $addressName }}"
                type="text"
                value="{{ old($addressName, $addressValue) }}"
                class="panel-input"
                placeholder="Search Google Maps for a gym, landmark, or address"
                maxlength="{{ $addressMax }}"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="{{ $id }}_location_results"
                data-location-address
            >
            @error($addressName)<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-60" data-location-search>
                <i class="ti ti-brand-google-maps"></i> Search Google Maps
            </button>
            <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-brand-300 hover:text-brand-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" data-location-current>
                <i class="ti ti-current-location"></i> Use my location
            </button>
            @if($preset)
                <button type="button" class="inline-flex h-11 items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" data-location-use-preset>
                    <i class="ti ti-building"></i> {{ $preset['label'] ?? 'Use gym address' }}
                </button>
            @endif
        </div>
    </div>

    <div id="{{ $id }}_location_results" role="listbox" aria-label="Google Maps address suggestions" class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900" data-location-results></div>

    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-900">
        <div class="atlas-location-map" data-location-map></div>
        <div class="pointer-events-none absolute left-3 top-3 z-[500] rounded-lg bg-white/95 px-3 py-2 text-xs font-medium text-slate-700 shadow dark:bg-slate-950/90 dark:text-slate-200">
            Search or click the map to place the pin
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-slate-500 dark:text-slate-400" data-location-status aria-live="polite">
            {{ $hasCoordinates ? 'Map pin saved.' : 'No map pin selected yet. The typed address can still be saved.' }}
        </p>
        <p class="text-[11px] text-slate-400">
            Powered by Google Maps
        </p>
    </div>

    @if($cityName || $stateName || $pincodeName || $countryName)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if($cityName)
                <x-form-input :name="$cityName" label="City" :value="old($cityName, $cityValue)" :required="$cityRequired" data-location-city />
            @endif
            @if($stateName)
                <x-form-input :name="$stateName" label="State" :value="old($stateName, $stateValue)" data-location-state />
            @endif
            @if($pincodeName)
                <x-form-input :name="$pincodeName" label="Pincode" :value="old($pincodeName, $pincodeValue)" data-location-pincode />
            @endif
            @if($countryName)
                <x-form-input :name="$countryName" label="Country" :value="old($countryName, $countryValue)" data-location-country />
            @endif
        </div>
    @endif

    <details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/60">
        <summary class="cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-300">Advanced coordinates</summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <x-form-input :name="$latitudeName" label="Latitude" type="number" step="0.0000001" :value="$latitude" data-location-latitude />
            <x-form-input :name="$longitudeName" label="Longitude" type="number" step="0.0000001" :value="$longitude" data-location-longitude />
        </div>
    </details>
</div>
