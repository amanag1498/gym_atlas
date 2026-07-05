@php
    $tiers = [
        ['eyebrow' => 'Members', 'title' => 'Free', 'copy' => 'Gym discovery, profile access, and the initial member experience stay free while the network grows.'],
        ['eyebrow' => 'Gyms', 'title' => 'Free onboarding initially', 'copy' => 'Gym setup, branches, trainers, members, attendance, and billing foundations can be activated during the rollout phase.'],
        ['eyebrow' => 'Trainers', 'title' => 'Free through gym initially', 'copy' => 'Trainers join through their connected gym and begin using coaching-linked workflows without separate launch pricing.'],
    ];

    $futureItems = [
        'Premium member features',
        'Advanced trainer tooling',
        'Promoted gym visibility layers',
    ];
@endphp

<x-public.layouts.app page-title="Pricing" page-description="Launch pricing strategy for members, gyms, and trainers with premium expansion paths.">
    <section class="hero-wrap hero-wrap-2" style="background-image: url('https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1800&q=80'); min-height: 34rem;">
        <div class="overlay"></div>
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 34rem; padding-top: 8rem; padding-bottom: 4.5rem;">
                <div class="col-xl-8 col-lg-10 ftco-animate">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Pricing</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(3rem, 6vw, 5.4rem); line-height: 0.98;">Simple launch pricing designed for adoption first.</h1>
                    <p class="atlas-hero-copy mb-0">Atlas stays easy to adopt while the network grows, with premium layers reserved for real leverage later.</p>
                    <p class="breadcrumbs mt-4 mb-0"><span class="mr-2"><a href="{{ route('public.home') }}">Home</a></span> <span>Pricing</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5 pb-3">
                <div class="col-lg-8 heading-section ftco-animate text-center public-reveal">
                    <h3 class="subheading">Launch pricing</h3>
                    <h2 class="mb-3">Free to start for the people who need the ecosystem working first</h2>
                    <p class="atlas-lead mb-0">The current model focuses on distribution, trust, and operational activation before adding advanced paid layers.</p>
                </div>
            </div>
            <div class="row">
                @foreach ($tiers as $tier)
                    <div class="col-lg-4 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div class="block-7 h-100 mb-4">
                            <div class="text-center">
                                <span class="excerpt d-block public-kicker mb-3">{{ $tier['eyebrow'] }}</span>
                                <span class="price d-block mb-2"><sup>{{ $loop->iteration === 1 ? '₹' : '' }}</sup> <span class="number">{{ $loop->iteration === 1 ? '0' : 'Free' }}</span></span>
                                <span class="pricing-text d-block">{{ $tier['title'] }}</span>
                                <p class="mt-4 px-lg-3">{{ $tier['copy'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Future lanes</h3>
                        <h2 class="mb-4">Premium only where leverage becomes real</h2>
                        <p class="atlas-lead mb-0">The platform is positioned to win on adoption first, then add paid capabilities where they genuinely improve visibility, growth, and operational control.</p>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 mt-5 mt-lg-0 ftco-animate public-reveal public-reveal-delay-1">
                    <div class="atlas-card p-4 p-md-5">
                        <div class="public-kicker mb-4">Expansion path</div>
                        <div style="display: grid; gap: 1rem;">
                            @foreach ($futureItems as $item)
                                <div class="d-flex align-items-start" style="gap: 0.85rem;">
                                    <span class="public-pill">0{{ $loop->iteration }}</span>
                                    <p class="mb-0" style="font-weight: 700; color: #0f172a; line-height: 1.65;">{{ $item }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
