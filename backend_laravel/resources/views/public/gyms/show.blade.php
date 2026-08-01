@php
    use App\Support\Scheduling\OperatingHours;
    use Illuminate\Support\Str;

    $priceSummary = $gym->fee_summary;
    $heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: asset('images/public-site/editorial/trainer-member-coaching.webp');
    $activeBranches = $gym->branches->where('is_active', true)->values();
    $trainers = $gym->trainerProfiles->merge($activeBranches->flatMap(fn ($branch) => $branch->trainerProfiles))->where('is_active', true)->unique('user_id')->values();
    $facilityNames = $gym->facilities->pluck('name')->merge($activeBranches->flatMap(fn ($branch) => $branch->facilities->pluck('name')))->filter()->unique()->values();
    $galleryImages = $gym->gymPhotos->where('type', 'gallery')->sortBy('sort_order')->pluck('image_url')->whenEmpty(fn ($images) => $images->merge(collect($gym->photo_urls ?? [])))->filter()->unique()->values();
    $gymSchedule = OperatingHours::normalize($gym->timings ?? [], $gym->weekly_off ?? []);
    $todayKey = strtolower(now($gym->timezone ?: config('app.timezone'))->englishDayOfWeek);
    $todayHours = OperatingHours::formatDaySlots($gymSchedule[$todayKey] ?? []);
    $publishedPlans = $gym->membershipPlans->where('status', 'active')->values();
    $addressLine = collect([$gym->address ?: $gym->address_line, $gym->city, $gym->state, $gym->pincode])->filter()->implode(', ');
    $mapQuery = trim(collect([$gym->name, $addressLine])->filter()->implode(', '));
    $mapsHref = $gym->latitude && $gym->longitude
        ? 'https://www.google.com/maps/search/?api=1&query='.$gym->latitude.','.$gym->longitude
        : ($mapQuery ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($mapQuery) : null);
    $contactTelHref = filled($gym->contact_number) ? 'tel:'.preg_replace('/[^0-9+]/', '', (string) $gym->contact_number) : null;
    $instagramValue = trim((string) $gym->instagram_profile);
    $instagramHref = $gym->contact_visible && $instagramValue !== ''
        ? (Str::startsWith($instagramValue, ['http://', 'https://']) ? $instagramValue : 'https://instagram.com/'.ltrim($instagramValue, '@/'))
        : null;
    $instagramHandle = $instagramHref ? '@'.trim((string) Str::of($instagramHref)->after('instagram.com/'), '/') : null;
    $gymSchema = array_filter([
        '@context' => 'https://schema.org', '@type' => 'HealthClub', 'name' => $gym->name,
        'url' => route('public.gyms.show', $gym->slug), 'image' => $heroImage,
        'description' => $gym->description ?: $gym->name.' public gym profile on Atlas.',
        'telephone' => $gym->contact_visible ? $gym->contact_number : null,
        'address' => $addressLine ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $gym->address ?: $gym->address_line, 'addressLocality' => $gym->city, 'addressRegion' => $gym->state, 'postalCode' => $gym->pincode, 'addressCountry' => 'IN']) : null,
        'geo' => $gym->latitude && $gym->longitude ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $gym->latitude, 'longitude' => (float) $gym->longitude] : null,
    ]);
    $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('public.home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Find Gyms', 'item' => route('public.gyms.index')],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $gym->name, 'item' => route('public.gyms.show', $gym->slug)],
    ]];
@endphp

<x-public.layouts.app :page-title="$gym->name" :page-description="$gym->description ?: $gym->name.' public profile'" :social-image="$heroImage" :social-image-alt="$gym->name.' gym profile'" :schemas="[$gymSchema, $breadcrumbSchema]">
    <div class="gym-profile-v3">
        <div class="public-container-wide">
            <nav class="gym-profile-v3__breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('public.gyms.index') }}"><i class="ti ti-arrow-left"></i> Find gyms</a><span>/</span><span>{{ $gym->name }}</span>
            </nav>

            @if (session('success'))<div class="gym-v3-success" role="status"><i class="ti ti-circle-check"></i>{{ session('success') }}</div>@endif

            <header class="gym-profile-v3__hero">
                <div class="gym-profile-v3__identity">
                    <div class="gym-profile-v3__badges">
                        @if($gym->is_verified)<span><i class="ti ti-rosette-discount-check"></i> Verified</span>@endif
                        @if($gym->is_featured)<span>Featured</span>@endif
                        @if($gym->trial_available)<span class="is-green">Trial available</span>@endif
                    </div>
                    <span class="gym-v3-kicker">{{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Atlas gym profile' }}</span>
                    <h1>{{ $gym->name }}</h1>
                    <p>{{ $gym->description ?: 'Review this gym’s published facilities, membership plans, branches, trainers and visit information.' }}</p>
                    <div class="gym-profile-v3__hero-actions">
                        @if($gym->trial_available || $gym->contact_visible)<a href="#request-trial" class="is-primary">{{ $gym->trial_available ? 'Request a trial' : 'Send enquiry' }}</a>@endif
                        @if($mapsHref)<a href="{{ $mapsHref }}" target="_blank" rel="noreferrer"><i class="ti ti-map-pin"></i> Directions</a>@endif
                    </div>
                </div>
                <div class="gym-profile-v3__cover" style="background-image:url('{{ $heroImage }}')">
                    <span class="gym-v3-open-state {{ $gym->is_open_now ? 'is-open' : '' }}">{{ $gym->is_open_now ? 'Open now' : 'Closed now' }}</span>
                </div>
            </header>

            <section class="gym-profile-v3__facts" aria-label="Gym summary">
                <div><i class="ti ti-clock"></i><span>Today<strong>{{ $todayHours }}</strong></span></div>
                <div><i class="ti ti-wallet"></i><span>Memberships<strong>{{ $gym->show_pricing && $priceSummary ? 'From ₹'.number_format((float) $priceSummary['min_price']) : 'Pricing on enquiry' }}</strong></span></div>
                <div><i class="ti ti-building-community"></i><span>Locations<strong>{{ $activeBranches->count() }} {{ str('branch')->plural($activeBranches->count()) }}</strong></span></div>
                <div><i class="ti ti-users"></i><span>Coaching<strong>{{ $trainers->count() }} public {{ str('trainer')->plural($trainers->count()) }}</strong></span></div>
            </section>

            <nav class="gym-profile-v3__nav" aria-label="Gym sections">
                <a href="#overview">Overview</a><a href="#plans">Plans</a><a href="#branches">Branches</a><a href="#gallery">Gallery</a><a href="#trainers">Trainers</a>
                @if($gym->trial_available || $gym->contact_visible)<a href="#request-trial" class="is-action">Enquire</a>@endif
            </nav>

            <div class="gym-profile-v3__layout">
                <div>
                    <section id="overview" class="gym-profile-v3__section">
                        <div class="gym-profile-v3__section-head"><div><span class="gym-v3-kicker">At a glance</span><h2>Everything you need before a visit</h2></div></div>
                        <div class="gym-profile-v3__overview-grid">
                            <div><h3>About</h3><p>{{ $gym->description ?: 'This gym has published its essential visit and membership information through Atlas.' }}</p></div>
                            <div><h3>Location</h3><p>{{ $addressLine ?: 'Address details have not been published yet.' }}</p>@if($mapsHref)<a href="{{ $mapsHref }}" target="_blank" rel="noreferrer">Open in maps <i class="ti ti-arrow-up-right"></i></a>@endif</div>
                        </div>
                        @if($facilityNames->isNotEmpty())
                            <div class="gym-profile-v3__facilities"><h3>Facilities</h3><div>@foreach($facilityNames as $facility)<span><i class="ti ti-circle-check"></i>{{ $facility }}</span>@endforeach</div></div>
                        @endif
                    </section>

                    <section id="plans" class="gym-profile-v3__section">
                        <div class="gym-profile-v3__section-head"><div><span class="gym-v3-kicker">Membership</span><h2>Plans and public pricing</h2></div>@if($gym->show_pricing && $priceSummary)<strong>From ₹{{ number_format((float) $priceSummary['min_price']) }}</strong>@endif</div>
                        @if($gym->show_pricing)
                            <div class="gym-profile-v3__plan-list">
                                @forelse($publishedPlans as $plan)
                                    <article><div><h3>{{ $plan->name }}</h3><p>{{ $plan->duration_label ?? $plan->duration_days.' days' }}@if($plan->description) · {{ Str::limit($plan->description, 80) }}@endif</p><div>@if((float)$plan->joining_fee > 0)<span>Joining ₹{{ number_format((float)$plan->joining_fee) }}</span>@endif @if($plan->pt_included)<span>PT included</span>@endif</div></div><strong>₹{{ number_format((float)$plan->plan_price) }}</strong></article>
                                @empty<p class="gym-v3-muted">No active membership plans are visible right now.</p>@endforelse
                            </div>
                        @else<p class="gym-v3-muted">This gym shares pricing after an enquiry or trial request.</p>@endif
                    </section>

                    <section id="branches" class="gym-profile-v3__section">
                        <div class="gym-profile-v3__section-head"><div><span class="gym-v3-kicker">Locations</span><h2>Branches</h2></div><strong>{{ $activeBranches->count() }}</strong></div>
                        <div class="gym-profile-v3__branch-list">
                            @forelse($activeBranches as $branch)
                                <article><i class="ti ti-building"></i><div><h3>{{ $branch->name }}</h3><p>{{ collect([$branch->address ?: $branch->address_line, $branch->city, $branch->state])->filter()->implode(', ') ?: 'Address not published' }}</p></div><span>{{ OperatingHours::formatDaySlots(OperatingHours::normalize($branch->timings ?? [], $branch->weekly_off ?? [])[$todayKey] ?? []) }}</span></article>
                            @empty<p class="gym-v3-muted">No additional branches are shown publicly.</p>@endforelse
                        </div>
                    </section>

                    <section id="gallery" class="gym-profile-v3__section">
                        <div class="gym-profile-v3__section-head"><div><span class="gym-v3-kicker">Inside the gym</span><h2>Gallery</h2></div><strong>{{ $galleryImages->count() }} images</strong></div>
                        @if($galleryImages->isNotEmpty())<div class="gym-profile-v3__gallery">@foreach($galleryImages->take(6) as $image)<a href="{{ $image }}" target="_blank" rel="noopener" style="background-image:url('{{ $image }}')" aria-label="Open gallery image {{ $loop->iteration }}"></a>@endforeach</div>@else<p class="gym-v3-muted">No public gallery images have been added yet.</p>@endif
                    </section>

                    <section id="trainers" class="gym-profile-v3__section">
                        <div class="gym-profile-v3__section-head"><div><span class="gym-v3-kicker">Coaching team</span><h2>Public trainers</h2></div><strong>{{ $trainers->count() }}</strong></div>
                        <div class="gym-profile-v3__trainer-list">
                            @forelse($trainers as $trainerProfile)
                                <article><div class="gym-profile-v3__avatar">{{ Str::upper(Str::substr($trainerProfile->user?->name ?: 'T', 0, 1)) }}</div><div><h3>{{ $trainerProfile->user?->name ?: 'Trainer' }}</h3><p>{{ $trainerProfile->specialization ?: collect($trainerProfile->specializations ?? [])->implode(', ') ?: 'Specialization not published' }}</p></div></article>
                            @empty<p class="gym-v3-muted">Trainer information is not available publicly yet.</p>@endforelse
                        </div>
                    </section>
                </div>

                <aside>
                    <div class="gym-profile-v3__visit-card">
                        <span class="gym-v3-kicker">Plan your visit</span><h2>{{ $gym->is_open_now ? 'Open now' : 'Closed now' }}</h2><p>{{ $todayHours }}</p>
                        <div class="gym-profile-v3__schedule">
                            @foreach($gymSchedule as $day => $slots)<div class="{{ $day === $todayKey ? 'is-today' : '' }}"><span>{{ OperatingHours::dayLabel($day) }}</span><strong>{{ OperatingHours::formatDaySlots($slots) }}</strong></div>@endforeach
                        </div>
                        <div class="gym-profile-v3__contact-list">
                            @if($mapsHref)<a href="{{ $mapsHref }}" target="_blank" rel="noreferrer"><i class="ti ti-map-pin"></i><span>Get directions<small>{{ $addressLine ?: 'Open map' }}</small></span></a>@endif
                            @if($gym->contact_visible && $contactTelHref)<a href="{{ $contactTelHref }}"><i class="ti ti-phone"></i><span>Call gym<small>{{ $gym->contact_number }}</small></span></a>@endif
                            @if($instagramHref)<a href="{{ $instagramHref }}" target="_blank" rel="noreferrer"><i class="ti ti-brand-instagram"></i><span>Instagram<small>{{ $instagramHandle }}</small></span></a>@endif
                        </div>
                        @if($gym->trial_available || $gym->contact_visible)<a class="gym-profile-v3__enquire" href="#request-trial">{{ $gym->trial_available ? 'Request a trial' : 'Send an enquiry' }}</a>@endif
                    </div>
                </aside>
            </div>

            @if($gym->trial_available || $gym->contact_visible)
                <section id="request-trial" class="gym-profile-v3__enquiry">
                    <div class="gym-profile-v3__enquiry-intro"><span class="gym-v3-kicker">Contact {{ $gym->name }}</span><h2>{{ $gym->trial_available ? 'Request a trial visit' : 'Send an enquiry' }}</h2><p>Share the essentials. The gym receives this directly in its Atlas lead workflow and can follow up with availability.</p><div><span><i class="ti ti-shield-check"></i> Sent securely</span><span><i class="ti ti-building"></i> Direct to the gym</span></div></div>
                    <div class="gym-profile-v3__form-wrap">
                        @if($errors->any())<div id="trial-form-errors" class="gym-v3-form-errors" role="alert"><strong>Please check the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                        <form method="POST" action="{{ route('public.gyms.trial-request', $gym->slug) }}" aria-label="Contact this gym" @if($errors->any()) aria-describedby="trial-form-errors" @endif>
                            @csrf
                            <input type="hidden" name="request_type" id="request_type" value="{{ old('request_type', $gym->trial_available ? 'trial' : 'contact') }}">
                            <div class="gym-profile-v3__form-grid">
                                <label><span>Name *</span><input name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror></label>
                                <label><span>Phone *</span><input name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" required @error('phone') aria-invalid="true" @enderror></label>
                                <label><span>Email</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" @error('email') aria-invalid="true" @enderror></label>
                                <label><span>Preferred branch</span><select name="branch_id"><option value="">Any branch</option>@foreach($activeBranches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                                <label><span>Preferred date</span><input name="preferred_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('preferred_date') }}"></label>
                                <label><span>Preferred time</span><input name="preferred_time" type="time" value="{{ old('preferred_time') }}"></label>
                                <label class="is-wide"><span>Anything the gym should know?</span><textarea name="notes" rows="3" placeholder="Optional notes">{{ old('notes') }}</textarea></label>
                            </div>
                            <div class="gym-profile-v3__form-actions">@if($gym->trial_available)<button type="submit" onclick="document.getElementById('request_type').value='trial'">Request trial</button>@endif<button type="submit" class="is-secondary" onclick="document.getElementById('request_type').value='contact'">{{ $gym->trial_available ? 'Send enquiry instead' : 'Send enquiry' }}</button></div>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-public.layouts.app>
