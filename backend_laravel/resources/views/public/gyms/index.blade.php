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

    $resultsLabel = number_format($gyms->total()).' gyms';
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
    <style>
        .atlas-discovery-page {
            background:
                radial-gradient(circle at 12% 0%, rgba(37, 99, 235, 0.14), transparent 25rem),
                radial-gradient(circle at 86% 5%, rgba(56, 189, 248, 0.12), transparent 24rem),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 48%, #ffffff 100%);
        }

        .atlas-discovery-hero {
            position: relative;
            min-height: 38rem;
            overflow: hidden;
            background-image:
                linear-gradient(110deg, rgba(8, 14, 28, 0.78) 0%, rgba(15, 23, 42, 0.50) 48%, rgba(37, 99, 235, 0.20) 100%),
                url('https://images.unsplash.com/photo-1517838277536-f5f99be501cd?auto=format&fit=crop&w=1800&q=80');
            background-size: cover;
            background-position: center;
        }

        .atlas-discovery-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 75% 20%, rgba(96, 165, 250, 0.35), transparent 18rem),
                linear-gradient(180deg, transparent 0%, rgba(248, 251, 255, 0.08) 100%);
            pointer-events: none;
        }

        .atlas-discovery-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 9rem;
            background: linear-gradient(180deg, transparent, #f8fbff);
            pointer-events: none;
        }

        .atlas-kicker-premium {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            color: #bfdbfe;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .atlas-kicker-premium::before {
            content: "";
            width: 0.48rem;
            height: 0.48rem;
            border-radius: 9999px;
            background: #60a5fa;
            box-shadow: 0 0 18px rgba(96, 165, 250, 0.95);
        }

        .atlas-command-wrap {
            position: relative;
            z-index: 6;
            margin-top: -8.5rem;
        }

        .atlas-command-card {
            overflow: hidden;
            border-radius: 2rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(255, 255, 255, 0.88);
            box-shadow:
                0 34px 100px rgba(15, 23, 42, 0.14),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(24px);
        }

        .atlas-command-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.12), transparent 22rem),
                linear-gradient(180deg, rgba(255, 255, 255, 0.62), transparent 48%);
            pointer-events: none;
        }

        .atlas-command-inner {
            position: relative;
            z-index: 2;
            padding: 1.35rem;
        }

        @media (min-width: 768px) {
            .atlas-command-inner {
                padding: 2rem;
            }
        }

        .atlas-command-header {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 1rem;
            margin-bottom: 1.2rem;
        }

        .atlas-command-panel {
            border-radius: 1.45rem;
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: rgba(248, 251, 255, 0.78);
            padding: 1.15rem;
        }

        .atlas-command-panel-dark {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.24), transparent 16rem),
                linear-gradient(135deg, #0f172a, #172554);
            border-color: rgba(191, 219, 254, 0.15);
            color: #ffffff;
        }

        .atlas-label-premium {
            display: block;
            margin-bottom: 0.52rem;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .atlas-command-panel-dark .atlas-label-premium {
            color: #bfdbfe;
        }

        .atlas-premium-input,
        .atlas-premium-select {
            width: 100%;
            min-height: 3.35rem;
            border-radius: 1.05rem !important;
            border: 1px solid rgba(148, 163, 184, 0.22) !important;
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.045);
            outline: none;
        }

        .atlas-premium-input::placeholder {
            color: #94a3b8;
            font-weight: 500;
        }

        .atlas-premium-input:focus,
        .atlas-premium-select:focus {
            border-color: rgba(37, 99, 235, 0.46) !important;
            box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.12) !important;
        }

        .atlas-search-shell {
            position: relative;
        }

        .atlas-search-shell .atlas-premium-input {
            padding-left: 3rem;
        }

        .atlas-search-icon {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            width: 1.15rem;
            height: 1.15rem;
            border-radius: 9999px;
            border: 2px solid #2563eb;
        }

        .atlas-search-icon::after {
            content: "";
            position: absolute;
            width: 0.45rem;
            height: 2px;
            right: -0.35rem;
            bottom: -0.18rem;
            background: #2563eb;
            transform: rotate(45deg);
            border-radius: 9999px;
        }

        .atlas-location-group {
            display: grid;
            grid-template-columns: 1fr 7rem;
            gap: 0.65rem;
        }

        .atlas-location-shell {
            border-radius: 1.2rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 251, 255, 0.88));
            padding: 0.8rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .atlas-location-copy {
            margin-top: 0.7rem;
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.6;
        }

        .atlas-location-status {
            font-weight: 800;
            color: #2563eb;
            white-space: nowrap;
        }

        .atlas-location-status[data-state="success"] {
            color: #0f766e;
        }

        .atlas-location-status[data-state="error"] {
            color: #be123c;
        }

        .atlas-btn-primary,
        .atlas-btn-soft,
        .atlas-btn-light {
            min-height: 3.15rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            padding: 0 1.25rem;
            font-size: 0.88rem;
            font-weight: 900;
            text-decoration: none !important;
            transition: all 180ms ease;
            white-space: nowrap;
        }

        .atlas-btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff !important;
            box-shadow: 0 18px 42px rgba(37, 99, 235, 0.24);
        }

        .atlas-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 26px 64px rgba(37, 99, 235, 0.32);
        }

        .atlas-btn-soft,
        .atlas-btn-light {
            background: #ffffff;
            border-color: rgba(148, 163, 184, 0.22);
            color: #334155 !important;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.045);
        }

        .atlas-btn-soft:hover,
        .atlas-btn-light:hover {
            color: #1d4ed8 !important;
            border-color: rgba(37, 99, 235, 0.26);
            transform: translateY(-1px);
        }

        .atlas-filter-bank {
            border-radius: 1.45rem;
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: rgba(255, 255, 255, 0.66);
            padding: 1rem;
        }

        .atlas-premium-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.48rem;
            min-height: 2.35rem;
            border-radius: 9999px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: #ffffff;
            color: #475569;
            padding: 0.54rem 0.86rem;
            font-size: 0.78rem;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.035);
            transition: all 180ms ease;
        }

        .atlas-premium-pill::before {
            content: "";
            width: 0.42rem;
            height: 0.42rem;
            border-radius: 9999px;
            background: #cbd5e1;
            transition: all 180ms ease;
        }

        input:checked + .atlas-premium-pill {
            background: #eff6ff;
            border-color: rgba(37, 99, 235, 0.35);
            color: #1d4ed8;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.10);
        }

        input:checked + .atlas-premium-pill::before {
            background: #2563eb;
            box-shadow: 0 0 0 0.3rem rgba(37, 99, 235, 0.12);
        }

        .atlas-active-chip {
            display: inline-flex;
            align-items: center;
            min-height: 2.1rem;
            border-radius: 9999px;
            background: #0f172a;
            color: #ffffff;
            padding: 0.42rem 0.72rem;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .atlas-details-premium summary {
            list-style: none;
            cursor: pointer;
            width: fit-content;
            color: #2563eb;
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
            outline: none;
        }

        .atlas-details-premium summary::-webkit-details-marker {
            display: none;
        }

        .atlas-results-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1rem;
            margin: 3.5rem 0 1.5rem;
        }

        .atlas-section-title {
            color: #0f172a;
            font-size: clamp(1.95rem, 3vw, 2.65rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.06em;
        }

        .atlas-card {
            height: 100%;
            position: relative;
            overflow: hidden;
            border-radius: 1.8rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.085);
            transition: transform 220ms ease, box-shadow 220ms ease, border-color 220ms ease;
        }

        .atlas-card:hover {
            transform: translateY(-6px);
            border-color: rgba(37, 99, 235, 0.26);
            box-shadow: 0 36px 95px rgba(15, 23, 42, 0.14);
        }

        .atlas-card-media {
            position: relative;
            min-height: 18rem;
            background-size: cover;
            background-position: center;
        }

        .atlas-card-media::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(15, 23, 42, 0.06), rgba(15, 23, 42, 0.56)),
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.20), transparent 12rem);
        }

        .atlas-card-body {
            padding: 1.45rem;
        }

        @media (min-width: 768px) {
            .atlas-card-body {
                padding: 1.7rem;
            }
        }

        .atlas-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            min-height: 2rem;
            border-radius: 9999px;
            padding: 0.38rem 0.66rem;
            background: rgba(255, 255, 255, 0.92);
            color: #1d4ed8;
            font-size: 0.7rem;
            font-weight: 900;
            backdrop-filter: blur(12px);
        }

        .atlas-badge-success {
            color: #047857;
        }

        .atlas-badge-gold {
            color: #92400e;
        }

        .atlas-badge-muted {
            color: #475569;
        }

        .atlas-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.65rem;
        }

        .atlas-metric {
            border-radius: 1.05rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: #f8fbff;
            padding: 0.85rem;
        }

        .atlas-metric span {
            display: block;
            color: #64748b;
            font-size: 0.62rem;
            font-weight: 900;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .atlas-metric strong {
            display: block;
            margin-top: 0.3rem;
            color: #0f172a;
            font-size: 1.02rem;
            font-weight: 900;
        }

        .atlas-empty {
            border-radius: 2rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.09);
            padding: 3.4rem 1.4rem;
        }

        .atlas-pagination-wrap nav {
            display: flex;
            justify-content: center;
        }

        @media (max-width: 991.98px) {
            .atlas-command-header {
                grid-template-columns: 1fr;
            }

            .atlas-results-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .atlas-location-copy {
                flex-direction: column;
            }
        }

        @media (max-width: 575.98px) {
            .atlas-command-wrap {
                margin-top: -6.5rem;
            }

            .atlas-location-group {
                grid-template-columns: 1fr;
            }

            .atlas-card-media {
                min-height: 15.5rem;
            }
        }
    </style>

    <section class="atlas-discovery-page">
        <section class="atlas-discovery-hero">
            <div class="container position-relative" style="z-index: 2;">
                <div class="row no-gutters align-items-end" style="min-height: 38rem; padding-top: 8rem; padding-bottom: 10rem;">
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
            <div class="container">
                <div class="atlas-command-wrap">
                    <div class="atlas-command-card position-relative ftco-animate">
                        <div class="atlas-command-inner">
                            <div class="atlas-command-header">
                                <div class="atlas-command-panel">
                                    <div class="atlas-label-premium">Discovery command center</div>
                                    <h2 class="mb-2" style="color: #0f172a; font-size: 2rem; font-weight: 900; letter-spacing: -0.055em; line-height: 1;">
                                        {{ $resultsLabel }} live public listings
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

                            <form method="GET">
                                <div class="row">
                                    <div class="col-lg-5">
                                        <div class="form-group mb-3">
                                            <label for="search" class="atlas-label-premium">Search</label>
                                            <div class="atlas-search-shell">
                                                <span class="atlas-search-icon"></span>
                                                <input id="search" name="search" value="{{ request('search') }}" placeholder="Gym name, locality, or keyword" class="atlas-premium-input">
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
                                            <label class="atlas-label-premium">Nearby</label>
                                            <div class="atlas-location-shell">
                                                <div class="atlas-location-group">
                                                    <button type="button" id="public-use-location" class="atlas-btn-light">Use current location</button>
                                                    <input id="distance" name="distance" type="number" min="1" step="1" value="{{ request('distance') }}" class="atlas-premium-input" placeholder="KM">
                                                </div>
                                                <div class="atlas-location-copy">
                                                    <span>Use your device location to prefill coordinates, then search within a tighter radius.</span>
                                                    <span id="public-location-status" class="atlas-location-status" data-state="idle">Radius optional</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="atlas-filter-bank mb-3">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap: 0.75rem;">
                                        <div>
                                            <div class="atlas-label-premium mb-1">Quick filters</div>
                                            <p class="mb-0" style="color: #64748b; font-size: 0.9rem;">Tap the signals that matter most.</p>
                                        </div>

                                        @if ($activeFilterCount > 0)
                                            <a href="{{ route('public.gyms.index') }}" class="atlas-btn-soft" style="min-height: 2.6rem;">Clear all</a>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap" style="gap: 0.55rem;">
                                        @foreach ($booleanFilters as $field => $label)
                                            <label class="mb-0">
                                                <input type="checkbox" name="{{ $field }}" value="1" class="d-none" @checked(request()->boolean($field))>
                                                <span class="atlas-premium-pill">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <details class="atlas-details-premium mb-4" {{ $activeFilterCount > 2 ? 'open' : '' }}>
                                    <summary>Advanced controls</summary>

                                    <div class="pt-4">
                                        <div class="row">
                                            <div class="col-lg-5">
                                                <div class="atlas-filter-bank h-100">
                                                    <div class="atlas-label-premium">Budget range</div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6 mb-3">
                                                            <input id="min_price" name="min_price" type="number" min="0" step="1" value="{{ request('min_price') }}" class="atlas-premium-input" placeholder="Min price">
                                                        </div>

                                                        <div class="form-group col-md-6 mb-3">
                                                            <input id="max_price" name="max_price" type="number" min="0" step="1" value="{{ request('max_price') }}" class="atlas-premium-input" placeholder="Max price">
                                                        </div>
                                                    </div>

                                                    <div class="atlas-label-premium mt-2">Exact location</div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6 mb-0">
                                                            <input id="latitude" name="latitude" type="number" step="any" value="{{ request('latitude') }}" class="atlas-premium-input" placeholder="Latitude">
                                                        </div>

                                                        <div class="form-group col-md-6 mb-0">
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
                                                            <label class="mb-0">
                                                                <input type="checkbox" name="facilities[]" value="{{ $facility->slug }}" class="d-none" @checked($selectedFacilities->contains($facility->slug))>
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
                                        <button class="atlas-btn-primary" type="submit">Apply filters</button>
                                        <a href="{{ route('public.gyms.index') }}" class="atlas-btn-soft">Reset</a>
                                    </div>

                                    <div style="color: #64748b; font-size: 0.9rem; font-weight: 700;">
                                        {{ $activeFilterCount > 0 ? $activeFilterCount.' active filters' : 'No filters applied' }}
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="atlas-results-head">
                    <div>
                        <div class="atlas-label-premium mb-2">Results</div>
                        <h2 class="atlas-section-title mb-0">Premium gym shortlist</h2>
                    </div>
                </div>

                <div class="row">
                    @forelse ($gyms as $gym)
                        @php
                            $priceSummary = $gym->fee_summary;
                            $startingPrice = $priceSummary['min_price'] ?? null;

                            $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: [
                                'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=1200&q=80',
                                'https://images.unsplash.com/photo-1517963879433-6ad2b056d712?auto=format&fit=crop&w=1200&q=80',
                            ][$loop->index % 6];

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

                        <div class="col-xl-4 col-lg-6 mb-4 ftco-animate">
                            <a href="{{ route('public.gyms.show', $gym->slug) }}" class="d-block text-decoration-none h-100">
                                <article class="atlas-card">
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

                                    <div class="atlas-card-body">
                                        <h3 class="mb-2" style="color: #0f172a; font-size: 1.42rem; font-weight: 900; line-height: 1.1; letter-spacing: -0.045em;">
                                            {{ $gym->name }}
                                        </h3>

                                        <p class="mb-3" style="color: #64748b; line-height: 1.65;">
                                            {{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Location available on profile' }}
                                        </p>

                                        <p class="mb-4" style="color: #475569; line-height: 1.78;">
                                            {{ \Illuminate\Support\Str::limit($gym->description ?: 'A public gym profile with discovery visibility, trainer presence, and structured trial flow.', 115) }}
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

                                            <span class="atlas-btn-primary" style="min-height: 2.75rem; padding: 0 1rem;">
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

                                <a href="{{ route('public.gyms.index') }}" class="atlas-btn-primary">Clear filters</a>
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
