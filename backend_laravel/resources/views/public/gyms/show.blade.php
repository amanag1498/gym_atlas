@php
    use App\Support\Scheduling\OperatingHours;
    use Illuminate\Support\Str;

    $priceSummary = $gym->fee_summary;
    $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: asset('images/public-site/editorial/trainer-member-coaching.webp');

    $activeBranches = $gym->branches->where('is_active', true)->values();

    $trainers = $gym->trainerProfiles
        ->merge($activeBranches->flatMap(fn ($branch) => $branch->trainerProfiles))
        ->filter(fn ($trainer) => $trainer->is_active)
        ->unique('user_id')
        ->values();

    $facilityNames = $gym->facilities
        ->pluck('name')
        ->merge($activeBranches->flatMap(fn ($branch) => $branch->facilities->pluck('name')))
        ->filter()
        ->unique()
        ->values();

    $galleryImages = $gym->gymPhotos
        ->where('type', 'gallery')
        ->sortBy('sort_order')
        ->pluck('image_url')
        ->whenEmpty(fn ($collection) => $collection->merge(collect($gym->photo_urls ?? [])))
        ->filter()
        ->unique()
        ->values();

    $gymSchedule = OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $todayKey = strtolower(now($gym->timezone ?: config('app.timezone'))->englishDayOfWeek);
    $todayHours = OperatingHours::formatDaySlots($gymSchedule[$todayKey] ?? []);

    $mapQuery = trim(collect([$gym->name, $gym->address ?: $gym->address_line, $gym->city, $gym->state])->filter()->implode(', '));
    $instagramHref = $gym->contact_visible && filled($gym->instagram_profile) ? $gym->instagram_profile : null;
    $instagramHandle = $instagramHref ? '@'.trim((string) Str::of($instagramHref)->after('instagram.com/'), '/') : null;
    $contactTelHref = filled($gym->contact_number)
        ? 'tel:'.preg_replace('/[^0-9+]/', '', (string) $gym->contact_number)
        : null;

    $mapsHref = $gym->latitude && $gym->longitude
        ? 'https://www.google.com/maps/search/?api=1&query='.$gym->latitude.','.$gym->longitude
        : ($mapQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($mapQuery) : null);

    $publishedPlans = $gym->membershipPlans->where('status', 'active')->values();

    $addressLine = collect([$gym->address ?: $gym->address_line, $gym->city, $gym->state, $gym->pincode])
        ->filter()
        ->implode(', ');

    $heroBadges = collect();

    if ($gym->is_verified) {
        $heroBadges->push(['label' => 'Verified', 'tone' => 'blue']);
    }

    if ($gym->is_featured) {
        $heroBadges->push(['label' => 'Featured', 'tone' => 'gold']);
    }

    if ($gym->is_promoted) {
        $heroBadges->push(['label' => 'Promoted', 'tone' => 'purple']);
    }

    if ($gym->trial_available) {
        $heroBadges->push(['label' => 'Trial available', 'tone' => 'green']);
    }

    $gymSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'HealthClub',
        'name' => $gym->name,
        'url' => route('public.gyms.show', $gym->slug),
        'image' => $heroImage,
        'description' => $gym->description ?: $gym->name.' public gym profile on Atlas.',
        'telephone' => $gym->contact_visible ? $gym->contact_number : null,
        'address' => $addressLine !== '' ? array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $gym->address ?: $gym->address_line,
            'addressLocality' => $gym->city,
            'addressRegion' => $gym->state,
            'postalCode' => $gym->pincode,
            'addressCountry' => 'IN',
        ]) : null,
        'geo' => $gym->latitude && $gym->longitude ? [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $gym->latitude,
            'longitude' => (float) $gym->longitude,
        ] : null,
    ]);

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('public.home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Find Gyms', 'item' => route('public.gyms.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $gym->name, 'item' => route('public.gyms.show', $gym->slug)],
        ],
    ];
@endphp

<x-public.layouts.app
    :page-title="$gym->name"
    :page-description="$gym->description ?: $gym->name.' public profile'"
    :social-image="$heroImage"
    :social-image-alt="$gym->name.' gym profile'"
    :schemas="[$gymSchema, $breadcrumbSchema]"
>

    <section class="atlas-profile-page">
        <section class="atlas-profile-hero" style="--atlas-profile-hero-image: url('{{ $heroImage }}');">
            <div class="public-container position-relative" style="z-index: 2;">
                <div class="atlas-profile-hero-content row no-gutters align-items-end" style="min-height: 42rem; padding-top: 8rem; padding-bottom: 10rem;">
                    <div class="col-xl-9 col-lg-10 ftco-animate">
                        @if (session('success'))
                            <div class="mb-4" role="status" aria-live="polite" style="border-radius: 1rem; border: 1px solid rgba(16,185,129,0.25); background: rgba(16,185,129,0.12); padding: 1rem; color: #d1fae5; font-weight: 700;">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if ($heroBadges->isNotEmpty())
                            <div class="d-flex flex-wrap mb-4" style="gap: 0.5rem;">
                                @foreach ($heroBadges as $badge)
                                    <span class="atlas-badge atlas-badge-{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="atlas-kicker mb-3">
                            {{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Public gym profile' }}
                        </div>

                        <h1 class="text-white mb-4" style="font-size: clamp(3.2rem, 7vw, 6.4rem); font-weight: 900; line-height: 0.9; letter-spacing: -0.085em;">
                            {{ $gym->name }}
                        </h1>

                        <p class="mb-0" style="max-width: 48rem; color: rgba(255,255,255,0.78); font-size: 1.06rem; line-height: 1.9;">
                            {{ $gym->description ?: 'Explore the public information, facilities, branches, plans, and contact options this gym has chosen to publish.' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="atlas-profile-shell">
            <div class="public-container-wide">
                <div class="atlas-quick-panel public-surface-premium mb-5 ftco-animate">
                    <div class="atlas-quick-panel-inner">
                        <div class="atlas-panel-content">
                            <div class="row align-items-stretch">
                                <div class="col-lg-8 mb-4 mb-lg-0">
                                    <div class="row h-100">
                                        <div class="col-6 col-md-3 mb-3 mb-md-0">
                                            <div class="atlas-metric">
                                                <span class="atlas-label">Today</span>
                                                <strong style="color: #0f172a; font-size: 1.02rem;">{{ $todayHours !== 'Closed' ? $todayHours : 'Closed' }}</strong>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3 mb-3 mb-md-0">
                                            <div class="atlas-metric">
                                                <span class="atlas-label">Starting</span>
                                                <strong style="color: #0f172a; font-size: 1.02rem;">
                                                    {{ $gym->show_pricing && $priceSummary ? '₹'.number_format((float) $priceSummary['min_price'], 0) : 'On enquiry' }}
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3">
                                            <div class="atlas-metric">
                                                <span class="atlas-label">Branches</span>
                                                <strong style="color: #0f172a; font-size: 1.02rem;">{{ $activeBranches->count() }}</strong>
                                            </div>
                                        </div>

                                        <div class="col-6 col-md-3">
                                            <div class="atlas-metric">
                                                <span class="atlas-label">Trainers</span>
                                                <strong style="color: #0f172a; font-size: 1.02rem;">{{ $trainers->count() }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="h-100 d-flex flex-wrap align-items-center justify-content-lg-end" style="gap: 0.7rem;">
                                        @if ($gym->trial_available)
                                            <a href="#request-trial" class="public-button public-button-primary atlas-action-primary">Request trial</a>
                                        @endif

                                        @if ($mapsHref)
                                            <a href="{{ $mapsHref }}" target="_blank" rel="noreferrer" class="public-button public-button-secondary atlas-action-secondary">Open maps</a>
                                        @endif

                                        @if ($contactTelHref && $gym->contact_visible)
                                            <a href="{{ $contactTelHref }}" class="public-button public-button-secondary atlas-action-secondary">Call gym</a>
                                        @endif

                                        <a href="#request-trial" class="public-button public-button-secondary atlas-action-secondary">{{ $gym->trial_available ? 'Contact gym' : 'Send enquiry' }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <nav class="atlas-profile-nav" aria-label="Gym profile sections">
                    <a href="#profile-overview">Overview</a>
                    <a href="#membership-plans">Plans</a>
                    <a href="#branches">Branches</a>
                    <a href="#gallery">Gallery</a>
                    <a href="#trainers">Trainers</a>
                    @if ($gym->trial_available || $gym->contact_visible)
                        <a class="atlas-profile-nav-action" href="#request-trial">{{ $gym->trial_available ? 'Request trial' : 'Enquire' }}</a>
                    @endif
                </nav>

                <div class="row mb-5">
                    <div class="col-lg-8 ftco-animate">
                        <div class="atlas-label mb-2">Profile</div>
                        <h2 class="atlas-section-title mb-3">About this gym</h2>
                        <p class="mb-0" style="color: #64748b; max-width: 45rem; line-height: 1.85;">
                            Review the published facilities, plans, locations, opening hours and visit options.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div id="profile-overview" class="atlas-card public-surface-premium mb-4 ftco-animate atlas-profile-overview-card">
                            <div class="atlas-card-inner">
                                <div class="row">
                                    <div class="col-md-6 mb-4 mb-md-0">
                                        <span class="atlas-label">Facilities</span>

                                        @if ($facilityNames->isNotEmpty())
                                            <div class="d-flex flex-wrap" style="gap: 0.5rem;">
                                                @foreach ($facilityNames as $facilityName)
                                                    <span class="atlas-pill">{{ $facilityName }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                                Facilities will appear here once they are published on the public listing.
                                            </p>
                                        @endif
                                    </div>

                                    <div class="col-md-6">
                                        <span class="atlas-label">Address</span>
                                        <p class="mb-0" style="color: #475569; line-height: 1.9;">
                                            {{ $addressLine ?: 'Address not published yet.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="membership-plans" class="atlas-card public-surface-premium mb-4 ftco-animate atlas-profile-plans-card">
                            <div class="atlas-card-inner">
                                <div class="d-flex flex-wrap justify-content-between align-items-start mb-4" style="gap: 1rem;">
                                    <div>
                                        <span class="atlas-label">Public pricing</span>
                                        <h3 class="mb-0" style="color: #0f172a; font-size: 1.7rem; font-weight: 900; letter-spacing: -0.04em;">Membership plans</h3>
                                    </div>

                                    @if ($gym->show_pricing && $priceSummary)
                                        <div class="text-md-right">
                                            <span class="atlas-label">Starts at</span>
                                            <div style="color: #0f172a; font-size: 1.3rem; font-weight: 900;">
                                                ₹{{ number_format((float) $priceSummary['min_price'], 0) }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                @if ($gym->show_pricing)
                                    <div class="row">
                                        @forelse ($publishedPlans as $plan)
                                            <div class="col-md-6 mb-4">
                                                <div class="atlas-plan">
                                                    <div class="d-flex justify-content-between align-items-start" style="gap: 1rem;">
                                                        <div>
                                                            <h4 class="mb-2" style="color: #0f172a; font-size: 1.13rem; font-weight: 900;">
                                                                {{ $plan->name }}
                                                            </h4>

                                                            <div style="color: #64748b; font-size: 0.9rem; font-weight: 700;">
                                                                {{ $plan->duration_label ?? ($plan->duration_days.' days') }}
                                                            </div>
                                                        </div>

                                                        <div class="text-right">
                                                            <div style="color: #0f172a; font-size: 1.35rem; font-weight: 900;">
                                                                ₹{{ number_format((float) $plan->plan_price, 0) }}
                                                            </div>

                                                            <div style="color: #64748b; font-size: 0.8rem;">
                                                                per cycle
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if ($plan->description)
                                                        <p class="mt-3 mb-0" style="color: #475569; line-height: 1.8;">
                                                            {{ Str::limit($plan->description, 120) }}
                                                        </p>
                                                    @endif

                                                    <div class="mt-4 d-flex flex-wrap" style="gap: 0.45rem;">
                                                        @if ((float) $plan->joining_fee > 0)
                                                            <span class="atlas-badge atlas-badge-gold">Joining ₹{{ number_format((float) $plan->joining_fee, 0) }}</span>
                                                        @endif

                                                        @if ($plan->pt_included)
                                                            <span class="atlas-badge atlas-badge-blue">PT included</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-12">
                                                <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                                    No active plans are visible publicly right now.
                                                </p>
                                            </div>
                                        @endforelse
                                    </div>
                                @else
                                    <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                        This gym shares pricing privately after inquiry or trial request.
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div id="branches" class="atlas-card public-surface-premium mb-4 ftco-animate atlas-profile-branches-card">
                            <div class="atlas-card-inner">
                                <span class="atlas-label">Branch network</span>

                                <div class="row">
                                    @forelse ($activeBranches as $branch)
                                        <div class="col-md-6 mb-4">
                                            <div class="atlas-plan">
                                                <h4 class="mb-2" style="color: #0f172a; font-size: 1.12rem; font-weight: 900;">
                                                    {{ $branch->name }}
                                                </h4>

                                                <p class="mb-3" style="color: #64748b; line-height: 1.75;">
                                                    {{ collect([$branch->address ?: $branch->address_line, $branch->city, $branch->state])->filter()->implode(', ') ?: 'Branch address not published' }}
                                                </p>

                                                <div style="color: #2563eb; font-size: 0.9rem; font-weight: 800;">
                                                    {{ OperatingHours::formatDaySlots(OperatingHours::normalize($branch->timings ?? [], $branch->weekly_off ?? [])[$todayKey] ?? []) }}
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                                No additional public branches are being shown right now.
                                            </p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div id="gallery" class="atlas-card public-surface-premium mb-4 ftco-animate atlas-profile-gallery-card">
                            <div class="atlas-card-inner">
                                <div class="d-flex flex-wrap justify-content-between align-items-start mb-4" style="gap: 1rem;">
                                    <div>
                                        <span class="atlas-label">Gallery</span>
                                        <h3 class="mb-0" style="color: #0f172a; font-size: 1.65rem; font-weight: 900; letter-spacing: -0.04em;">Look inside the space</h3>
                                    </div>

                                    <div style="color: #64748b; font-size: 0.9rem; font-weight: 800;">
                                        {{ $galleryImages->count() }} image{{ $galleryImages->count() === 1 ? '' : 's' }}
                                    </div>
                                </div>

                                @if ($galleryImages->isNotEmpty())
                                    <div class="row">
                                        @foreach ($galleryImages->take(6) as $image)
                                            <div class="col-md-4 mb-4">
                                                <a href="{{ $image }}" class="gallery atlas-gallery-tile" style="background-image: url('{{ $image }}');" target="_blank" rel="noopener" aria-label="Open {{ $gym->name }} gallery image {{ $loop->iteration }} in a new tab"></a>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                        Gallery images will appear here when this gym publishes public photos.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 atlas-profile-sidebar">
                        <div class="atlas-card atlas-card-dark public-surface-dark mb-4 ftco-animate atlas-profile-visit-card">
                            <div class="atlas-card-inner">
                                <span class="atlas-label">Visit planning</span>

                                <div class="row">
                                    <div class="col-sm-6 mb-4">
                                        <div class="atlas-label mb-2">Status</div>
                                        <div style="color: #ffffff; font-size: 1.08rem; font-weight: 900;">
                                            {{ $gym->is_open_now ? 'Open now' : 'Closed now' }}
                                        </div>
                                    </div>

                                    <div class="col-sm-6 mb-4">
                                        <div class="atlas-label mb-2">Contact</div>
                                        <div style="color: #ffffff; font-size: 1.08rem; font-weight: 900;">
                                            {{ $gym->contact_visible && $gym->contact_number ? $gym->contact_number : ($gym->contact_visible ? 'Lead form active' : 'By request') }}
                                        </div>
                                    </div>

                                    <div class="col-sm-6 mb-4 mb-sm-0">
                                        <div class="atlas-label mb-2">Plans</div>
                                        <div style="color: #ffffff; font-size: 1.08rem; font-weight: 900;">
                                            {{ $publishedPlans->count() }}
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <div class="atlas-label mb-2">Trainers</div>
                                        <div style="color: #ffffff; font-size: 1.08rem; font-weight: 900;">
                                            {{ $trainers->count() }}
                                        </div>
                                    </div>
                                </div>

                                @if (($gym->contact_visible && $gym->contact_number) || $instagramHref)
                                    <div class="mt-2 d-flex flex-wrap" style="gap: 0.6rem;">
                                        @if ($gym->contact_visible && $gym->contact_number)
                                            <a href="{{ $contactTelHref }}" class="atlas-pill" style="text-decoration: none;">{{ $gym->contact_number }}</a>
                                        @endif
                                        @if ($instagramHref)
                                            <a href="{{ $instagramHref }}" target="_blank" rel="noreferrer" class="atlas-pill" style="text-decoration: none;">{{ $instagramHandle }}</a>
                                        @endif
                                    </div>
                                @endif

                                <div class="mt-4 pt-4" style="border-top: 1px solid rgba(255,255,255,0.08);">
                                    @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                                        <div class="atlas-timetable-row">
                                            <span style="color: rgba(255,255,255,0.88); text-transform: capitalize; font-weight: 800;">
                                                {{ $day }}
                                            </span>

                                            <span style="color: rgba(255,255,255,0.66); text-align: right;">
                                                {{ OperatingHours::formatDaySlots($gymSchedule[$day] ?? []) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div id="trainers" class="atlas-card public-surface-premium mb-4 ftco-animate atlas-profile-trainers-card">
                            <div class="atlas-card-inner">
                                <div class="d-flex flex-wrap justify-content-between align-items-start mb-4" style="gap: 1rem;">
                                    <div>
                                        <span class="atlas-label">Coaching side</span>
                                        <h3 class="mb-0" style="color: #0f172a; font-size: 1.55rem; font-weight: 900; letter-spacing: -0.04em;">Visible trainers</h3>
                                    </div>

                                    <div style="color: #64748b; font-size: 0.9rem; font-weight: 800;">
                                        {{ $trainers->count() }} listed
                                    </div>
                                </div>

                                @forelse ($trainers->take(5) as $trainerProfile)
                                    <div class="d-flex align-items-start py-3" style="border-bottom: 1px solid rgba(148,163,184,0.14); gap: 0.9rem;">
                                        <div class="atlas-trainer-avatar">
                                            {{ Str::of($trainerProfile->user?->name ?? 'T')->substr(0, 1) }}
                                        </div>

                                        <div>
                                            <div style="color: #0f172a; font-weight: 900;">
                                                {{ $trainerProfile->user?->name ?? 'Trainer' }}
                                            </div>

                                            <div style="color: #64748b; font-size: 0.92rem; line-height: 1.7;">
                                                {{ $trainerProfile->specialization ?: collect($trainerProfile->specializations ?? [])->implode(', ') ?: 'Specialization not published' }}
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="mb-0" style="color: #64748b; line-height: 1.8;">
                                        Trainer information is not available publicly for this gym yet.
                                    </p>
                                @endforelse
                            </div>
                        </div>

                        @if ($gym->trial_available || $gym->contact_visible)
                            <div id="request-trial" class="atlas-card atlas-card-dark public-surface-dark ftco-animate">
                                <div class="atlas-card-inner">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3" style="gap: 1rem;">
                                        <div>
                                            <span class="atlas-label">Lead intake</span>
                                            <h3 class="mb-0" style="color: #ffffff; font-size: 1.55rem; font-weight: 900; letter-spacing: -0.04em;">
                                                {{ $gym->trial_available ? 'Request a trial' : 'Contact this gym' }}
                                            </h3>
                                        </div>

                                        <div style="color: rgba(255,255,255,0.62); font-size: 0.9rem; font-weight: 800;">
                                            Gym lead workflow
                                        </div>
                                    </div>

                                    <p class="mb-4" style="color: rgba(255,255,255,0.72); line-height: 1.8;">
                                        Send your details with the branch and preferred slot if you have one. The gym team receives this directly inside its lead workflow.
                                    </p>

                                    @if ($errors->any())
                                        <div id="trial-form-errors" class="mb-4" role="alert" style="border-radius: 1rem; border: 1px solid rgba(244,63,94,0.25); background: rgba(244,63,94,0.12); padding: 1rem; color: #ffe4e6;">
                                            <div style="font-weight: 900;">Please correct the highlighted trial request fields.</div>
                                            <ul class="mt-3 mb-0">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('public.gyms.trial-request', $gym->slug) }}" aria-label="{{ $gym->trial_available ? 'Request a gym trial' : 'Contact this gym' }}" @if ($errors->any()) aria-describedby="trial-form-errors" @endif>
                                        @csrf
                                        <input type="hidden" name="request_type" id="request_type" value="{{ old('request_type', $gym->trial_available ? 'trial' : 'contact') }}">

                                        <div class="form-row">
                                            <div class="form-group col-md-6 mb-3">
                                                <label for="name" class="atlas-form-label">Your name <span aria-hidden="true">*</span></label>
                                                <input id="name" name="name" value="{{ old('name') }}" class="atlas-form-control" placeholder="Enter your full name" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                                                @error('name')
                                                    <span id="name-error" class="atlas-field-error">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="form-group col-md-6 mb-3">
                                                <label for="phone" class="atlas-form-label">Phone number <span aria-hidden="true">*</span></label>
                                                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" class="atlas-form-control" placeholder="Enter your phone number" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                                                @error('phone')
                                                    <span id="phone-error" class="atlas-field-error">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="email" class="atlas-form-label">Email address <span class="font-weight-normal">(optional)</span></label>
                                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="atlas-form-control" placeholder="Enter your email address" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                                            @error('email')
                                                <span id="email-error" class="atlas-field-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="branch_id" class="atlas-form-label">Preferred branch <span class="font-weight-normal">(optional)</span></label>
                                            <select id="branch_id" name="branch_id" class="atlas-form-control" @error('branch_id') aria-invalid="true" aria-describedby="branch-id-error" @enderror>
                                                <option value="">Any available branch</option>
                                                @foreach ($activeBranches as $branch)
                                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('branch_id')
                                                <span id="branch-id-error" class="atlas-field-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6 mb-3">
                                                <label for="preferred_date" class="atlas-form-label">Preferred date <span class="font-weight-normal">(optional)</span></label>
                                                <input id="preferred_date" name="preferred_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('preferred_date') }}" class="atlas-form-control" @error('preferred_date') aria-invalid="true" aria-describedby="preferred-date-error" @enderror>
                                                @error('preferred_date')
                                                    <span id="preferred-date-error" class="atlas-field-error">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="form-group col-md-6 mb-3">
                                                <label for="preferred_time" class="atlas-form-label">Preferred time <span class="font-weight-normal">(optional)</span></label>
                                                <input id="preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time') }}" class="atlas-form-control" @error('preferred_time') aria-invalid="true" aria-describedby="preferred-time-error" @enderror>
                                                @error('preferred_time')
                                                    <span id="preferred-time-error" class="atlas-field-error">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <label for="notes" class="atlas-form-label">Notes <span class="font-weight-normal">(optional)</span></label>
                                            <textarea id="notes" name="notes" rows="4" class="atlas-form-control" placeholder="Anything the gym should know before reaching out" @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes') }}</textarea>
                                            @error('notes')
                                                <span id="notes-error" class="atlas-field-error">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="d-flex flex-wrap" style="gap: 0.75rem;">
                                            @if ($gym->trial_available)
                                                <button class="public-button public-button-primary atlas-action-primary" type="submit" onclick="document.getElementById('request_type').value='trial'">Request trial</button>
                                            @endif
                                            <button class="public-button public-button-secondary atlas-action-secondary" type="submit" onclick="document.getElementById('request_type').value='contact'">
                                                {{ $gym->trial_available ? 'Send enquiry' : 'Contact gym' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </section>
</x-public.layouts.app>
