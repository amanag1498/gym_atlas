@php
    use Illuminate\Support\Str;

    $selectedFacilities = collect((array) request('facilities', []))->filter()->map(fn ($value) => (string) $value)->values();
    $booleanFilters = [
        'open_now' => 'Open now',
        'trial_available' => 'Trial available',
        'verified_only' => 'Verified only',
        'women_friendly' => 'Women friendly',
        'women_only' => 'Women only',
        'personal_training_available' => 'Personal training',
        'featured_only' => 'Featured',
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
    $activeFilterChips = collect();
    if (filled(request('search'))) $activeFilterChips->push('Search: '.request('search'));
    if (filled(request('city'))) $activeFilterChips->push(request('city'));
    if (filled(request('min_price'))) $activeFilterChips->push('From ₹'.number_format((float) request('min_price')));
    if (filled(request('max_price'))) $activeFilterChips->push('Up to ₹'.number_format((float) request('max_price')));
    if (filled(request('distance'))) $activeFilterChips->push('Within '.request('distance').' km');
    foreach ($booleanFilters as $field => $label) if (request()->boolean($field)) $activeFilterChips->push($label);
    foreach ($selectedFacilities as $slug) $activeFilterChips->push(str($slug)->replace('-', ' ')->title());
@endphp

<x-public.layouts.app page-title="Find Gyms" page-description="Discover active public gyms, compare facilities and pricing, and request a trial.">
    <div class="gym-discovery-v3">
        <header class="gym-discovery-v3__hero">
            <div class="public-container-wide">
                <div class="gym-discovery-v3__hero-grid">
                    <div>
                        <span class="gym-v3-kicker">Atlas gym discovery</span>
                        <h1>Find the right gym, faster.</h1>
                        <p>Compare real locations, current facilities, public pricing and visit options without opening oversized listing cards.</p>
                    </div>
                    <form method="GET" action="{{ route('public.gyms.index') }}" class="gym-v3-hero-search" aria-label="Quick gym search">
                        <label>
                            <span>Search gyms</span>
                            <input name="search" type="search" value="{{ request('search') }}" placeholder="Gym or locality" autocomplete="off">
                        </label>
                        <label>
                            <span>City</span>
                            <select name="city">
                                <option value="">Every city</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit">Search</button>
                    </form>
                </div>
            </div>
        </header>

        <section class="public-container-wide gym-discovery-v3__main">
            <div class="gym-v3-toolbar">
                <div>
                    <span class="gym-v3-kicker">Available now</span>
                    <h2>{{ number_format($gyms->total()) }} {{ str('gym')->plural($gyms->total()) }} found</h2>
                    <p>{{ $activeFilterCount ? 'Your shortlist uses '.$activeFilterCount.' active '.str('filter')->plural($activeFilterCount).'.' : 'Browse every approved public listing or refine the results.' }}</p>
                </div>
                <button type="button" class="gym-v3-filter-button" data-gym-filter-open aria-controls="gym-filter-panel" aria-expanded="false">
                    <i class="ti ti-adjustments-horizontal"></i> Filters
                    @if ($activeFilterCount)<span>{{ $activeFilterCount }}</span>@endif
                </button>
            </div>

            @if ($activeFilterChips->isNotEmpty())
                <div class="gym-v3-active-filters" aria-label="Active filters">
                    @foreach ($activeFilterChips as $chip)<span>{{ $chip }}</span>@endforeach
                    <a href="{{ route('public.gyms.index') }}">Clear all</a>
                </div>
            @endif

            <button type="button" class="atlas-filter-drawer-backdrop" data-gym-filter-backdrop aria-label="Close gym filters" tabindex="-1"></button>

            <div class="gym-v3-layout">
                <aside>
                    <form id="gym-filter-panel" method="GET" action="{{ route('public.gyms.index') }}" class="gym-v3-filters" data-gym-filter-panel aria-label="Filter public gyms">
                        <div class="atlas-filter-drawer-header">
                            <div><span class="gym-v3-kicker">Refine</span><strong id="gym-filter-drawer-title">Filter gyms</strong></div>
                            <button type="button" class="atlas-filter-drawer-close" data-gym-filter-close aria-label="Close filters"><i class="ti ti-x"></i></button>
                        </div>

                        <div class="gym-v3-filter-heading">
                            <div><span class="gym-v3-kicker">Refine results</span><h3>Filters</h3></div>
                            @if ($activeFilterCount)<a href="{{ route('public.gyms.index') }}">Reset</a>@endif
                        </div>

                        <label class="gym-v3-field"><span>Search</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Name or locality"></label>
                        <label class="gym-v3-field"><span>City</span><select name="city"><option value="">All cities</option>@foreach ($cities as $city)<option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>@endforeach</select></label>

                        <fieldset class="gym-v3-filter-group">
                            <legend>Popular choices</legend>
                            @foreach ($booleanFilters as $field => $label)
                                <label class="gym-v3-check"><input type="checkbox" name="{{ $field }}" value="1" @checked(request()->boolean($field))><span>{{ $label }}</span></label>
                            @endforeach
                        </fieldset>

                        <details class="gym-v3-filter-details" @if(filled(request('min_price')) || filled(request('max_price'))) open @endif>
                            <summary>Budget</summary>
                            <div class="gym-v3-field-row">
                                <label class="gym-v3-field"><span>Minimum</span><input name="min_price" type="number" min="0" value="{{ request('min_price') }}" placeholder="₹ Min"></label>
                                <label class="gym-v3-field"><span>Maximum</span><input name="max_price" type="number" min="0" value="{{ request('max_price') }}" placeholder="₹ Max"></label>
                            </div>
                        </details>

                        @if ($facilities->isNotEmpty())
                            <details class="gym-v3-filter-details" @if($selectedFacilities->isNotEmpty()) open @endif>
                                <summary>Facilities</summary>
                                <div class="gym-v3-facility-checks">
                                    @foreach ($facilities as $facility)
                                        <label class="gym-v3-check"><input type="checkbox" name="facilities[]" value="{{ $facility->slug }}" @checked($selectedFacilities->contains($facility->slug))><span>{{ $facility->name }}</span></label>
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <details class="gym-v3-filter-details" @if(filled(request('distance'))) open @endif>
                            <summary>Nearby</summary>
                            <button type="button" id="public-use-location" class="gym-v3-location-button"><i class="ti ti-current-location"></i> Use my location</button>
                            <label class="gym-v3-field"><span>Maximum distance</span><input id="distance" name="distance" type="number" min="1" step="1" value="{{ request('distance') }}" placeholder="Kilometres"></label>
                            <input id="latitude" name="latitude" type="hidden" value="{{ request('latitude') }}">
                            <input id="longitude" name="longitude" type="hidden" value="{{ request('longitude') }}">
                            <span id="public-location-status" class="gym-v3-location-status" role="status" aria-live="polite">Location is optional</span>
                        </details>

                        <div class="gym-v3-filter-actions"><button type="submit">Show gyms</button><a href="{{ route('public.gyms.index') }}">Reset</a></div>
                    </form>
                </aside>

                <section aria-label="Gym search results">
                    <div class="gym-v3-results-grid">
                        @forelse ($gyms as $gym)
                            @php
                                $priceSummary = $gym->fee_summary;
                                $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: [
                                    asset('images/public-site/editorial/trainer-member-coaching.webp'),
                                    asset('images/public-site/editorial/gym-operations-team.webp'),
                                    asset('images/product/member/feature-network-1024.webp'),
                                ][$loop->index % 3];
                                $facilityNames = $gym->facilities->pluck('name')->merge($gym->branches->flatMap(fn ($branch) => $branch->facilities->pluck('name')))->filter()->unique()->take(3)->values();
                                $trainerCount = $gym->trainerProfiles->merge($gym->branches->flatMap(fn ($branch) => $branch->trainerProfiles))->where('is_active', true)->unique('user_id')->count();
                                $branchCount = $gym->branches->where('is_active', true)->count();
                            @endphp
                            <article class="gym-v3-card">
                                <a href="{{ route('public.gyms.show', $gym->slug) }}" class="gym-v3-card__media" style="background-image:url('{{ $heroImage }}')" aria-label="View {{ $gym->name }}">
                                    <div class="gym-v3-card__badges">
                                        @if ($gym->is_verified)<span><i class="ti ti-rosette-discount-check"></i> Verified</span>@endif
                                        @if ($gym->trial_available)<span class="is-green">Trial</span>@endif
                                    </div>
                                    <span class="gym-v3-open-state {{ $gym->is_open_now ? 'is-open' : '' }}">{{ $gym->is_open_now ? 'Open now' : 'Closed' }}</span>
                                </a>
                                <div class="gym-v3-card__body">
                                    <div class="gym-v3-card__title-row"><div><h3><a href="{{ route('public.gyms.show', $gym->slug) }}">{{ $gym->name }}</a></h3><p><i class="ti ti-map-pin"></i> {{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Location on profile' }}</p></div>@if(filled($gym->distance_km))<span class="gym-v3-distance">{{ number_format((float) $gym->distance_km, 1) }} km</span>@endif</div>
                                    <p class="gym-v3-card__description">{{ Str::limit($gym->description ?: 'View facilities, branches, plans and ways to contact this gym.', 96) }}</p>
                                    @if ($facilityNames->isNotEmpty())<div class="gym-v3-card__facilities">@foreach($facilityNames as $facility)<span>{{ $facility }}</span>@endforeach</div>@endif
                                    <div class="gym-v3-card__facts">
                                        <div><span>Starting</span><strong>{{ $gym->show_pricing && $priceSummary ? '₹'.number_format((float) $priceSummary['min_price']) : 'Enquire' }}</strong></div>
                                        <div><span>Branches</span><strong>{{ $branchCount }}</strong></div>
                                        <div><span>Trainers</span><strong>{{ $trainerCount }}</strong></div>
                                        <a href="{{ route('public.gyms.show', $gym->slug) }}" aria-label="Open {{ $gym->name }} profile"><i class="ti ti-arrow-up-right"></i></a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="gym-v3-empty"><i class="ti ti-map-search"></i><h3>No gyms match these filters</h3><p>Try a broader city, fewer facilities, or a wider price range.</p><a href="{{ route('public.gyms.index') }}">Clear filters</a></div>
                        @endforelse
                    </div>
                    @if ($gyms->hasPages())<div class="atlas-pagination-wrap gym-v3-pagination">{{ $gyms->links() }}</div>@endif
                </section>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const button = document.getElementById('public-use-location');
            const status = document.getElementById('public-location-status');
            const latitude = document.getElementById('latitude');
            const longitude = document.getElementById('longitude');
            const distance = document.getElementById('distance');
            if (!button || !latitude || !longitude) return;
            button.addEventListener('click', function () {
                if (!navigator.geolocation) { status.textContent = 'Location is not supported'; return; }
                button.disabled = true; status.textContent = 'Finding your location…';
                navigator.geolocation.getCurrentPosition(function (position) {
                    latitude.value = position.coords.latitude.toFixed(6);
                    longitude.value = position.coords.longitude.toFixed(6);
                    if (!distance.value) distance.value = 5;
                    button.disabled = false; button.innerHTML = '<i class="ti ti-circle-check"></i> Location ready'; status.textContent = 'Coordinates added';
                }, function () { button.disabled = false; status.textContent = 'Location access was not granted'; });
            });
        });
    </script>
</x-public.layouts.app>
