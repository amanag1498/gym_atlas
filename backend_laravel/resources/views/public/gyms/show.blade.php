@php
    use App\Support\Scheduling\OperatingHours;
    use Illuminate\Support\Str;

    $priceSummary = $gym->fee_summary;
    $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1800&q=80';

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
@endphp

<x-public.layouts.app :page-title="$gym->name" :page-description="$gym->description ?: $gym->name.' public profile'">
    <style>
        .atlas-profile-page {
            background:
                radial-gradient(circle at 12% 0%, rgba(37, 99, 235, 0.14), transparent 28rem),
                radial-gradient(circle at 88% 7%, rgba(56, 189, 248, 0.12), transparent 26rem),
                linear-gradient(180deg, #f8fbff 0%, #eef5ff 45%, #ffffff 100%);
        }

        .atlas-profile-hero {
            position: relative;
            min-height: 42rem;
            overflow: hidden;
            background-image:
                linear-gradient(110deg, rgba(8, 14, 28, 0.82) 0%, rgba(15, 23, 42, 0.54) 48%, rgba(37, 99, 235, 0.18) 100%),
                url('{{ $heroImage }}');
            background-size: cover;
            background-position: center;
        }

        .atlas-profile-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 78% 18%, rgba(96, 165, 250, 0.36), transparent 19rem),
                linear-gradient(180deg, transparent 0%, rgba(248, 251, 255, 0.08) 100%);
            pointer-events: none;
        }

        .atlas-profile-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 9rem;
            background: linear-gradient(180deg, transparent, #f8fbff);
            pointer-events: none;
        }

        .atlas-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.58rem;
            color: #bfdbfe;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .atlas-kicker::before {
            content: "";
            width: 0.48rem;
            height: 0.48rem;
            border-radius: 9999px;
            background: #60a5fa;
            box-shadow: 0 0 18px rgba(96, 165, 250, 0.95);
        }

        .atlas-profile-shell {
            position: relative;
            z-index: 6;
            margin-top: -7.5rem;
            padding-bottom: 5rem;
        }

        .atlas-quick-panel {
            overflow: hidden;
            border-radius: 2rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(255, 255, 255, 0.90);
            box-shadow:
                0 34px 100px rgba(15, 23, 42, 0.14),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(24px);
        }

        .atlas-quick-panel-inner {
            position: relative;
            padding: 1.35rem;
        }

        @media (min-width: 768px) {
            .atlas-quick-panel-inner {
                padding: 2rem;
            }
        }

        .atlas-quick-panel-inner::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.10), transparent 22rem),
                linear-gradient(180deg, rgba(255,255,255,0.62), transparent 50%);
            pointer-events: none;
        }

        .atlas-panel-content {
            position: relative;
            z-index: 2;
        }

        .atlas-badge {
            display: inline-flex;
            align-items: center;
            min-height: 2rem;
            border-radius: 9999px;
            padding: 0.4rem 0.72rem;
            background: rgba(255, 255, 255, 0.92);
            color: #1d4ed8;
            font-size: 0.72rem;
            font-weight: 900;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(12px);
        }

        .atlas-badge-blue {
            color: #1d4ed8;
        }

        .atlas-badge-green {
            color: #047857;
        }

        .atlas-badge-gold {
            color: #92400e;
        }

        .atlas-badge-purple {
            color: #6d28d9;
        }

        .atlas-badge-muted {
            color: #475569;
        }

        .atlas-action-primary,
        .atlas-action-secondary {
            min-height: 3.2rem;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            padding: 0 1.25rem;
            font-size: 0.9rem;
            font-weight: 900;
            text-decoration: none !important;
            transition: all 180ms ease;
            white-space: nowrap;
        }

        .atlas-action-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff !important;
            box-shadow: 0 18px 42px rgba(37, 99, 235, 0.25);
        }

        .atlas-action-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 26px 64px rgba(37, 99, 235, 0.33);
        }

        .atlas-action-secondary {
            background: #ffffff;
            border-color: rgba(148, 163, 184, 0.22);
            color: #334155 !important;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.045);
        }

        .atlas-action-secondary:hover {
            color: #1d4ed8 !important;
            border-color: rgba(37, 99, 235, 0.26);
            transform: translateY(-1px);
        }

        .atlas-metric {
            border-radius: 1.25rem;
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: rgba(248, 251, 255, 0.82);
            padding: 1rem;
            height: 100%;
        }

        .atlas-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .atlas-section-title {
            color: #0f172a;
            font-size: clamp(1.95rem, 3vw, 2.65rem);
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.06em;
        }

        .atlas-card {
            position: relative;
            overflow: hidden;
            border-radius: 1.75rem;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.085);
        }

        .atlas-card-dark {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.22), transparent 18rem),
                linear-gradient(135deg, #0f172a 0%, #172554 58%, #0b1120 100%);
            border-color: rgba(191, 219, 254, 0.15);
            color: #ffffff;
            box-shadow: 0 28px 86px rgba(15, 23, 42, 0.20);
        }

        .atlas-card-inner {
            padding: 1.35rem;
        }

        @media (min-width: 768px) {
            .atlas-card-inner {
                padding: 1.8rem;
            }
        }

        .atlas-card-dark .atlas-label {
            color: #bfdbfe;
        }

        .atlas-card-dark p,
        .atlas-card-dark li {
            color: rgba(255, 255, 255, 0.72);
        }

        .atlas-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 2.1rem;
            border-radius: 9999px;
            border: 1px solid rgba(148, 163, 184, 0.20);
            background: #ffffff;
            color: #475569;
            padding: 0.42rem 0.7rem;
            font-size: 0.72rem;
            font-weight: 900;
        }

        .atlas-pill::before {
            content: "";
            width: 0.4rem;
            height: 0.4rem;
            border-radius: 9999px;
            background: #2563eb;
        }

        .atlas-card-dark .atlas-pill {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.08);
            color: #e2e8f0;
        }

        .atlas-plan {
            height: 100%;
            border-radius: 1.25rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background: #f8fbff;
            padding: 1.15rem;
        }

        .atlas-card-dark .atlas-plan {
            border-color: rgba(255, 255, 255, 0.10);
            background: rgba(255, 255, 255, 0.07);
        }

        .atlas-timetable-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .atlas-card-dark .atlas-timetable-row {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .atlas-gallery-tile {
            display: block;
            min-height: 13.5rem;
            border-radius: 1.2rem;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            position: relative;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
        }

        .atlas-gallery-tile::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(15,23,42,0.04), rgba(15,23,42,0.34));
        }

        .atlas-trainer-avatar {
            width: 3rem;
            height: 3rem;
            flex: 0 0 auto;
            border-radius: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #2563eb;
            font-weight: 900;
        }

        .atlas-card-dark .atlas-trainer-avatar {
            background: rgba(255,255,255,0.09);
            color: #dbeafe;
        }

        .atlas-form-control {
            width: 100%;
            min-height: 3.25rem;
            border-radius: 1rem !important;
            border: 1px solid rgba(148, 163, 184, 0.22) !important;
            background: #ffffff !important;
            color: #0f172a !important;
            padding: 0 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.045);
            outline: none;
        }

        textarea.atlas-form-control {
            padding-top: 0.9rem;
            min-height: 7rem;
        }

        .atlas-form-control::placeholder {
            color: #94a3b8;
            font-weight: 500;
        }

        .atlas-form-control:focus {
            border-color: rgba(37, 99, 235, 0.46) !important;
            box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.12) !important;
        }

        .atlas-card-dark .atlas-form-control {
            border-color: rgba(255, 255, 255, 0.12) !important;
            background: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            box-shadow: none;
        }

        .atlas-card-dark .atlas-form-control::placeholder {
            color: rgba(255, 255, 255, 0.50);
        }

        @media (max-width: 991.98px) {
            .atlas-profile-shell {
                margin-top: -5.5rem;
            }
        }
    </style>

    <section class="atlas-profile-page">
        <section class="atlas-profile-hero">
            <div class="container position-relative" style="z-index: 2;">
                <div class="row no-gutters align-items-end" style="min-height: 42rem; padding-top: 8rem; padding-bottom: 10rem;">
                    <div class="col-xl-9 col-lg-10 ftco-animate">
                        @if (session('success'))
                            <div class="mb-4" style="border-radius: 1rem; border: 1px solid rgba(16,185,129,0.25); background: rgba(16,185,129,0.12); padding: 1rem; color: #d1fae5; font-weight: 700;">
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
                            {{ $gym->description ?: 'A premium public gym profile with live discovery visibility, trainer presence, visible facilities, and a structured trial path.' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="atlas-profile-shell">
            <div class="container">
                <div class="atlas-quick-panel mb-5 ftco-animate">
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
                                            <a href="#request-trial" class="atlas-action-primary">Request trial</a>
                                        @endif

                                        @if ($mapsHref)
                                            <a href="{{ $mapsHref }}" target="_blank" rel="noreferrer" class="atlas-action-secondary">Open maps</a>
                                        @endif

                                        @if ($contactTelHref && $gym->contact_visible)
                                            <a href="{{ $contactTelHref }}" class="atlas-action-secondary">Call gym</a>
                                        @endif

                                        <a href="#request-trial" class="atlas-action-secondary">{{ $gym->trial_available ? 'Contact gym' : 'Send enquiry' }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-lg-8 ftco-animate">
                        <div class="atlas-label mb-2">Profile</div>
                        <h2 class="atlas-section-title mb-3">Everything needed before a visit.</h2>
                        <p class="mb-0" style="color: #64748b; max-width: 45rem; line-height: 1.85;">
                            Facilities, pricing, locations, timings, trainers, gallery, and trial request are arranged in one premium decision flow.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="atlas-card mb-4 ftco-animate">
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

                        <div class="atlas-card mb-4 ftco-animate">
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

                        <div class="atlas-card mb-4 ftco-animate">
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

                        <div class="atlas-card mb-4 ftco-animate">
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
                                                <a href="{{ $image }}" class="gallery image-popup atlas-gallery-tile" style="background-image: url('{{ $image }}');"></a>
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

                    <div class="col-lg-5">
                        <div class="atlas-card atlas-card-dark mb-4 ftco-animate">
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

                        <div class="atlas-card mb-4 ftco-animate">
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
                            <div id="request-trial" class="atlas-card atlas-card-dark ftco-animate">
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
                                        <div class="mb-4" style="border-radius: 1rem; border: 1px solid rgba(244,63,94,0.25); background: rgba(244,63,94,0.12); padding: 1rem; color: #ffe4e6;">
                                            <div style="font-weight: 900;">Please correct the highlighted trial request fields.</div>
                                            <ul class="mt-3 mb-0">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('public.gyms.trial-request', $gym->slug) }}">
                                        @csrf
                                        <input type="hidden" name="request_type" id="request_type" value="{{ old('request_type', $gym->trial_available ? 'trial' : 'contact') }}">

                                        <div class="form-row">
                                            <div class="form-group col-md-6 mb-3">
                                                <input id="name" name="name" value="{{ old('name') }}" class="atlas-form-control" placeholder="Your name">
                                            </div>

                                            <div class="form-group col-md-6 mb-3">
                                                <input id="phone" name="phone" value="{{ old('phone') }}" class="atlas-form-control" placeholder="Phone number">
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <input id="email" name="email" type="email" value="{{ old('email') }}" class="atlas-form-control" placeholder="Email address">
                                        </div>

                                        <div class="form-group mb-3">
                                            <select id="branch_id" name="branch_id" class="atlas-form-control">
                                                <option value="">Any available branch</option>
                                                @foreach ($activeBranches as $branch)
                                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-6 mb-3">
                                                <input id="preferred_date" name="preferred_date" type="date" value="{{ old('preferred_date') }}" class="atlas-form-control">
                                            </div>

                                            <div class="form-group col-md-6 mb-3">
                                                <input id="preferred_time" name="preferred_time" type="time" value="{{ old('preferred_time') }}" class="atlas-form-control">
                                            </div>
                                        </div>

                                        <div class="form-group mb-4">
                                            <textarea id="notes" name="notes" rows="4" class="atlas-form-control" placeholder="Anything the gym should know before reaching out">{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="d-flex flex-wrap" style="gap: 0.75rem;">
                                            @if ($gym->trial_available)
                                                <button class="atlas-action-primary" type="submit" onclick="document.getElementById('request_type').value='trial'">Request trial</button>
                                            @endif
                                            <button class="atlas-action-secondary" type="submit" onclick="document.getElementById('request_type').value='contact'">
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
