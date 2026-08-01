@php
    $selectedFacilities = collect((array) request('facilities', []))
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->map(fn ($value) => (string) $value)
        ->values();

    $booleanFilters = [
        'trial_available' => 'Trial',
        'verified_only' => 'Verified',
        'featured_only' => 'Featured',
        'women_friendly' => 'Women friendly',
        'women_only' => 'Women only',
        'personal_training_available' => 'Personal training',
        'open_now' => 'Open now',
    ];

    $activeFilterCount = collect([
        filled(request('search')),
        filled(request('city')),
        filled(request('min_price')),
        filled(request('max_price')),
        filled(request('distance')),
        filled(request('latitude')) && filled(request('longitude')),
    ])->filter()->count()
        + $selectedFacilities->count()
        + collect($booleanFilters)->keys()->filter(fn ($field) => request()->boolean($field))->count();

    $resultsLabel = number_format($gyms->total()).' live public '.str('listing')->plural($gyms->total());
    $startingPricePool = $gyms->pluck('fee_summary.min_price')->filter()->map(fn ($price) => (float) $price);
    $startingPriceFloor = $startingPricePool->isNotEmpty() ? 'From ₹'.number_format($startingPricePool->min(), 0) : 'Pricing on enquiry';

    $activeFilterChips = collect();

    if (filled(request('search'))) {
        $activeFilterChips->push('Search: '.request('search'));
    }

    if (filled(request('city'))) {
        $activeFilterChips->push('City: '.request('city'));
    }

    if (filled(request('min_price'))) {
        $activeFilterChips->push('Min ₹'.number_format((float) request('min_price'), 0));
    }

    if (filled(request('max_price'))) {
        $activeFilterChips->push('Max ₹'.number_format((float) request('max_price'), 0));
    }

    if (filled(request('distance'))) {
        $activeFilterChips->push('Within '.request('distance').' km');
    }

    foreach ($booleanFilters as $field => $label) {
        if (request()->boolean($field)) {
            $activeFilterChips->push($label);
        }
    }

    foreach ($selectedFacilities as $facilitySlug) {
        $activeFilterChips->push(str($facilitySlug)->replace('-', ' ')->title());
    }
@endphp

<x-public.layouts.app page-title="Find Gyms" page-description="Discover active public gyms, compare facilities, view pricing where available, and request fitness trials.">

    <section class="atlas-discovery-page">
        <section class="atlas-discovery-hero" style="--atlas-discovery-hero-image: url('{{ asset('images/public-site/editorial/trainer-member-coaching.webp') }}');">
            <div class="public-container position-relative" style="z-index: 2;">
                <div class="atlas-hero-content row no-gutters align-items-end" style="min-height: 38rem; padding-top: 8rem; padding-bottom: 10rem;">
                    <div class="col-xl-8 col-lg-10 ftco-animate">
                        <div class="atlas-kicker-premium mb-3">Gym discovery</div>

                        <h1 class="text-white mb-3" style="font-size: clamp(3.1rem, 6vw, 5.9rem); font-weight: 900; line-height: 0.9; letter-spacing: -0.085em;">
                            Find the right gym, faster.
                        </h1>

                        <p class="mb-0" style="max-width: 44rem; color: rgba(255,255,255,0.78); font-size: 1.05rem; line-height: 1.9;">
                            Search live gym profiles with pricing signals, trials, facilities, trainers, locations, and clear shortlist controls.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="pb-5">
            <div class="public-container-wide">
                <div class="atlas-command-wrap">
                    <div class="atlas-command-card public-surface-premium position-relative ftco-animate">
                        <div class="atlas-command-inner">
                            <div class="atlas-command-header">
                                <div class="atlas-command-panel">
                                    <div class="atlas-label-premium">Available gyms</div>
                                    <h2 class="mb-2" style="color: #0f172a; font-size: 2rem; font-weight: 900; letter-spacing: -0.055em; line-height: 1;">
                                        {{ $resultsLabel }}
                                    </h2>
                                    <p class="mb-0" style="color: #64748b; line-height: 1.75;">
                                        {{ $activeFilterCount > 0 ? $activeFilterCount.' filters are shaping this shortlist.' : 'Start broad, then refine by city, price, facilities, trial availability, and distance.' }}
                                    </p>
                                </div>

                                <div class="atlas-command-panel atlas-command-panel-dark">
                                    <div class="atlas-label-premium">Visible pricing</div>
                                    <div style="font-size: 1.65rem; font-weight: 900; letter-spacing: -0.05em;">
                                        {{ $startingPriceFloor }}
                                    </div>
                                    <p class="mb-0 mt-2" style="color: rgba(255,255,255,0.68); line-height: 1.7;">
                                        Based on currently visible membership plans.
                                    </p>
                                </div>
                            </div>

                            <div class="atlas-mobile-filter-summary" aria-label="Current gym filters">
                                <div>
                                    <span class="atlas-label-premium mb-1">Refine your shortlist</span>
                                    <strong>{{ $activeFilterCount > 0 ? $activeFilterCount.' active '.str('filter')->plural($activeFilterCount) : 'All gyms' }}</strong>
                                    <span>{{ $activeFilterCount > 0 ? $activeFilterChips->take(3)->implode(' · ') : 'City, facilities, pricing and more' }}</span>
                                </div>

                                <button
                                    type="button"
                                    class="public-button public-button-primary"
                                    data-gym-filter-open
                                    aria-controls="gym-filter-panel"
                                    aria-expanded="false"
                                >
                                    Filters
                                    @if ($activeFilterCount > 0)
                                        <span class="atlas-mobile-filter-count" aria-label="{{ $activeFilterCount }} active filters">{{ $activeFilterCount }}</span>
                                    @endif
                                </button>
                            </div>

                            <button type="button" class="atlas-filter-drawer-backdrop" data-gym-filter-backdrop aria-label="Close gym filters" tabindex="-1"></button>

                            <form
                                id="gym-filter-panel"
                                method="GET"
                                action="{{ route('public.gyms.index') }}"
                                aria-label="Filter public gyms"
                                data-gym-filter-panel
                            >
                                <div class="atlas-filter-drawer-header">
                                    <div>
                                        <span class="atlas-label-premium mb-1">Gym discovery</span>
                                        <strong id="gym-filter-drawer-title">Filter your shortlist</strong>
                                    </div>

                                    <button type="button" class="atlas-filter-drawer-close" data-gym-filter-close aria-label="Close gym filters">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <div class="form-group mb-3">
                                            <label for="search" class="atlas-label-premium">Search</label>
                                            <div class="atlas-search-shell">
                                                <span class="atlas-search-icon"></span>
                                                <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Gym name, locality, or keyword" class="atlas-premium-input" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <div class="form-group mb-3">
                                            <label for="city" class="atlas-label-premium">City</label>
                                            <select id="city" name="city" class="atlas-premium-select">
                                                <option value="">All cities</option>
                                                @foreach ($cities as $city)
                                                    <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group mb-3">
                                            <label for="distance" class="atlas-label-premium">Nearby radius</label>
                                            <div class="atlas-location-shell">
                                                <div class="atlas-location-group">
                                                    <button type="button" id="public-use-location" class="public-button public-button-secondary">Use current location</button>
                                                    <input id="distance" name="distance" type="number" min="1" step="1" value="{{ request('distance') }}" class="atlas-premium-input" placeholder="Distance" inputmode="numeric" aria-describedby="distance-help public-location-status">
                                                </div>
                                                <div class="atlas-location-copy">
                                                    <span id="distance-help">Use your device location to prefill coordinates, then search within a tighter radius.</span>
                                                    <span id="public-location-status" class="atlas-location-status" data-state="idle" role="status" aria-live="polite">Radius optional</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <details class="atlas-details-premium mb-4" {{ $activeFilterCount > 2 ? 'open' : '' }}>
                                    <summary>More filters</summary>

                                    <div class="pt-4">
                                        <fieldset class="atlas-filter-bank mb-3" aria-labelledby="quick-filter-title">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem;">
                                                <div>
                                                    <div id="quick-filter-title" class="atlas-label-premium mb-1">Quick filters</div>
                                                    <p class="mb-0" style="color: #64748b; font-size: 0.9rem;">Choose only the signals that matter to you.</p>
                                                </div>

                                                @if ($activeFilterCount > 0)
                                                    <a href="{{ route('public.gyms.index') }}" class="public-button public-button-secondary">Clear all</a>
                                                @endif
                                            </div>

                                            <div class="d-flex flex-wrap" style="gap: 0.55rem;">
                                                @foreach ($booleanFilters as $field => $label)
                                                    <label class="atlas-filter-option mb-0">
                                                        <input type="checkbox" name="{{ $field }}" value="1" class="sr-only" @checked(request()->boolean($field))>
                                                        <span class="atlas-premium-pill">{{ $label }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>

                                        <div class="row">
                                            <div class="col-lg-5">
                                                <div class="atlas-filter-bank h-100">
                                                    <div class="atlas-label-premium">Budget range</div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6 mb-3">
                                                            <label for="min_price" class="atlas-label-premium">Minimum price</label>
                                                            <input id="min_price" name="min_price" type="number" min="0" step="1" value="{{ request('min_price') }}" class="atlas-premium-input" placeholder="Min price">
                                                        </div>

                                                        <div class="form-group col-md-6 mb-3">
                                                            <label for="max_price" class="atlas-label-premium">Maximum price</label>
                                                            <input id="max_price" name="max_price" type="number" min="0" step="1" value="{{ request('max_price') }}" class="atlas-premium-input" placeholder="Max price">
                                                        </div>
                                                    </div>

                                                    <div class="atlas-label-premium mt-2">Exact location</div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6 mb-0">
                                                            <label for="latitude" class="atlas-label-premium">Latitude</label>
                                                            <input id="latitude" name="latitude" type="number" step="any" value="{{ request('latitude') }}" class="atlas-premium-input" placeholder="Latitude">
                                                        </div>

                                                        <div class="form-group col-md-6 mb-0">
                                                            <label for="longitude" class="atlas-label-premium">Longitude</label>
                                                            <input id="longitude" name="longitude" type="number" step="any" value="{{ request('longitude') }}" class="atlas-premium-input" placeholder="Longitude">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-lg-7 mt-3 mt-lg-0">
                                                <div class="atlas-filter-bank h-100">
                                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem;">
                                                        <div>
                                                            <div class="atlas-label-premium mb-1">Facilities</div>
                                                            <p class="mb-0" style="color: #64748b; font-size: 0.9rem;">Choose gym features you want to compare.</p>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex flex-wrap" style="gap: 0.55rem;">
                                                        @foreach ($facilities as $facility)
                                                            <label class="atlas-filter-option mb-0">
                                                                <input type="checkbox" name="facilities[]" value="{{ $facility->slug }}" class="sr-only" @checked($selectedFacilities->contains($facility->slug))>
                                                                <span class="atlas-premium-pill">{{ $facility->name }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                @if ($activeFilterChips->isNotEmpty())
                                    <div class="mb-4 d-flex flex-wrap" style="gap: 0.5rem;">
                                        @foreach ($activeFilterChips->take(12) as $chip)
                                            <span class="atlas-active-chip">{{ $chip }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 0.9rem;">
                                    <div class="d-flex flex-wrap" style="gap: 0.7rem;">
                                        <button class="public-button public-button-primary" type="submit">Apply filters</button>
                                        <a href="{{ route('public.gyms.index') }}" class="public-button public-button-secondary">Reset</a>
                                    </div>

                                    <div style="color: #64748b; font-size: 0.9rem; font-weight: 700;">
                                        {{ $activeFilterCount > 0 ? $activeFilterCount.' active filters' : 'No filters applied' }}
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="atlas-results-head atlas-gym-results-heading">
                    <div>
                        <div class="atlas-label-premium mb-2">Results</div>
                        <h2 class="atlas-section-title mb-0">Explore gyms</h2>
                        <p class="mb-0 mt-2">Compare locations, facilities and pricing, then open a profile for complete details.</p>
                    </div>
                </div>

                <div class="row atlas-gym-results-grid">
                    @forelse ($gyms as $gym)
                        @php
                            $priceSummary = $gym->fee_summary;
                            $startingPrice = $priceSummary['min_price'] ?? null;

                            $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: [
                                asset('images/public-site/editorial/trainer-member-coaching.webp'),
                                asset('images/public-site/editorial/gym-operations-team.webp'),
                                asset('images/product/member/feature-network-1024.webp'),
                            ][$loop->index % 3];

                            $facilityNames = $gym->facilities
                                ->pluck('name')
                                ->merge($gym->branches->flatMap(fn ($branch) => $branch->facilities->pluck('name')))
                                ->filter()
                                ->unique()
                                ->take(4)
                                ->values();

                            $trainerCount = $gym->trainerProfiles
                                ->merge($gym->branches->flatMap(fn ($branch) => $branch->trainerProfiles))
                                ->filter(fn ($trainer) => $trainer->is_active)
                                ->unique('user_id')
                                ->count();

                            $branchCount = $gym->branches->where('is_active', true)->count();
                        @endphp

                        <div class="col-xl-4 col-lg-6 mb-4 ftco-animate atlas-gym-result">
                            <a href="{{ route('public.gyms.show', $gym->slug) }}" class="d-block text-decoration-none h-100">
                                <article class="atlas-card public-surface-premium atlas-gym-result-card">
                                    <div class="atlas-card-media" style="background-image: url('{{ $heroImage }}');">
                                        <div class="position-absolute d-flex flex-wrap" style="left: 1rem; right: 1rem; top: 1rem; z-index: 2; gap: 0.45rem;">
                                            @if ($gym->is_verified)
                                                <span class="atlas-badge">Verified</span>
                                            @endif

                                            @if ($gym->is_featured)
                                                <span class="atlas-badge atlas-badge-gold">Featured</span>
                                            @endif

                                            @if ($gym->trial_available)
                                                <span class="atlas-badge atlas-badge-success">Trial</span>
                                            @endif
                                        </div>

                                        <div class="position-absolute d-flex align-items-center justify-content-between" style="left: 1rem; right: 1rem; bottom: 1rem; z-index: 2; gap: 1rem;">
                                            <span class="atlas-badge {{ $gym->is_open_now ? 'atlas-badge-success' : 'atlas-badge-muted' }}">
                                                {{ $gym->is_open_now ? 'Open now' : 'Closed' }}
                                            </span>

                                            @if (filled($gym->distance_km))
                                                <span class="atlas-badge">{{ number_format((float) $gym->distance_km, 1) }} km</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="atlas-card-body atlas-gym-result-body">
                                        <h3 class="mb-2" style="color: #0f172a; font-size: 1.42rem; font-weight: 900; line-height: 1.1; letter-spacing: -0.045em;">
                                            {{ $gym->name }}
                                        </h3>

                                        <p class="mb-3" style="color: #64748b; line-height: 1.65;">
                                            {{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Location available on profile' }}
                                        </p>

                                        <p class="mb-4" style="color: #475569; line-height: 1.78;">
                                            {{ \Illuminate\Support\Str::limit($gym->description ?: 'Explore the public information, facilities, branches, plans, and contact options this gym has chosen to publish.', 115) }}
                                        </p>

                                        @if ($gym->contact_visible && ($gym->contact_number || $gym->instagram_profile))
                                            <div class="mb-4 d-flex flex-wrap" style="gap: 0.45rem;">
                                                @if ($gym->contact_number)
                                                    <span class="atlas-premium-pill" style="min-height: 2rem; padding: 0.42rem 0.66rem; font-size: 0.7rem; cursor: default;">{{ $gym->contact_number }}</span>
                                                @endif
                                                @if ($gym->instagram_profile)
                                                    <span class="atlas-premium-pill" style="min-height: 2rem; padding: 0.42rem 0.66rem; font-size: 0.7rem; cursor: default;">{{ '@'.str($gym->instagram_profile)->after('instagram.com/') }}</span>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="atlas-metrics mb-4">
                                            <div class="atlas-metric">
                                                <span>Starting</span>
                                                <strong>{{ $gym->show_pricing && $startingPrice !== null ? '₹'.number_format((float) $startingPrice, 0) : 'Ask' }}</strong>
                                            </div>

                                            <div class="atlas-metric">
                                                <span>Branches</span>
                                                <strong>{{ $branchCount }}</strong>
                                            </div>

                                            <div class="atlas-metric">
                                                <span>Trainers</span>
                                                <strong>{{ $trainerCount }}</strong>
                                            </div>
                                        </div>

                                        @if ($facilityNames->isNotEmpty())
                                            <div class="mb-4 d-flex flex-wrap" style="gap: 0.45rem;">
                                                @foreach ($facilityNames as $facilityName)
                                                    <span class="atlas-premium-pill" style="min-height: 2rem; padding: 0.42rem 0.66rem; font-size: 0.7rem; cursor: default;">{{ $facilityName }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="d-flex align-items-center justify-content-between" style="gap: 1rem;">
                                            <span style="color: #64748b; font-size: 0.9rem; font-weight: 800;">
                                                View profile
                                            </span>

                                            <span class="public-button public-button-primary">
                                                Open
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </a>
                        </div>
                    @empty
                        <div class="col-12 ftco-animate">
                            <div class="atlas-empty text-center">
                                <div class="atlas-label-premium mb-2">No results</div>

                                <h3 class="mb-3" style="color: #0f172a; font-size: 2.1rem; font-weight: 900; letter-spacing: -0.05em;">
                                    No gyms matched this shortlist.
                                </h3>

                                <p class="mb-4" style="color: #64748b; line-height: 1.8;">
                                    Try widening the price range, removing facility filters, or choosing a broader city.
                                </p>

                                <a href="{{ route('public.gyms.index') }}" class="public-button public-button-primary">Clear filters</a>
                            </div>
                        </div>
                    @endforelse
                </div>

                @if ($gyms->count() > 0)
                    <div class="atlas-pagination-wrap pt-3">
                        {{ $gyms->links() }}
                    </div>
                @endif
            </div>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const locationButton = document.getElementById('public-use-location');
            const locationStatus = document.getElementById('public-location-status');
            const latitudeField = document.getElementById('latitude');
            const longitudeField = document.getElementById('longitude');
            const distanceField = document.getElementById('distance');

            if (!locationButton || !latitudeField || !longitudeField) {
                return;
            }

            const updateLocationStatus = function (message, state) {
                if (!locationStatus) {
                    return;
                }

                locationStatus.textContent = message;
                locationStatus.dataset.state = state;
            };

            locationButton.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    locationButton.textContent = 'Unavailable';
                    updateLocationStatus('Location not supported', 'error');
                    return;
                }

                locationButton.disabled = true;
                locationButton.textContent = 'Locating...';
                updateLocationStatus('Requesting location...', 'idle');

                navigator.geolocation.getCurrentPosition(function (position) {
                    latitudeField.value = position.coords.latitude.toFixed(6);
                    longitudeField.value = position.coords.longitude.toFixed(6);

                    if (distanceField && !distanceField.value) {
                        distanceField.value = 5;
                    }

                    locationButton.textContent = 'Location ready';
                    locationButton.disabled = false;
                    updateLocationStatus('Coordinates added', 'success');
                }, function () {
                    locationButton.textContent = 'Try again';
                    locationButton.disabled = false;
                    updateLocationStatus('Location access denied', 'error');
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                });
            });
        });
    </script>
</x-public.layouts.app>
