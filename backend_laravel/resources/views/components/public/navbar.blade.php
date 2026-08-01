@php
    $routeHref = static fn (string $route, string $fallback): string => Route::has($route)
        ? route($route)
        : route($fallback);

    $productLinks = [
        [
            'label' => 'Product overview',
            'description' => 'See how every Atlas experience connects.',
            'route' => 'public.product',
            'fallback' => 'public.home',
            'icon' => 'sparkles',
        ],
        [
            'label' => 'Member App',
            'description' => 'Discover gyms and stay consistent with your plan.',
            'route' => 'public.member-app',
            'fallback' => 'public.gyms.index',
            'icon' => 'member',
        ],
        [
            'label' => 'Trainer App',
            'description' => 'Plan, coach and follow every member journey.',
            'route' => 'public.trainer-app',
            'fallback' => 'public.for-trainers',
            'icon' => 'trainer',
        ],
        [
            'label' => 'Gym Management',
            'description' => 'Run members, teams, attendance and revenue.',
            'route' => 'public.gym-management',
            'fallback' => 'public.for-gyms',
            'icon' => 'building',
        ],
        [
            'label' => 'How Atlas works',
            'description' => 'Follow the complete discovery-to-progress journey.',
            'route' => 'public.how-it-works',
            'fallback' => 'public.home',
            'icon' => 'flow',
        ],
    ];

    $productRouteNames = array_column($productLinks, 'route');
    $isProductActive = request()->routeIs(...$productRouteNames)
        || request()->routeIs('public.for-gyms', 'public.for-trainers');
@endphp

<header class="public-header" data-public-header>
    <div class="public-header-inner public-container-wide">
        <a class="public-brand" href="{{ route('public.home') }}" aria-label="Atlas home">
            <img
                class="public-brand-image"
                src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}"
                width="40"
                height="40"
                alt=""
            >
            <span class="public-brand-copy">
                <span class="public-brand-name">ATLAS</span>
                <span class="public-brand-tagline">Fitness, connected</span>
            </span>
        </a>

        <button
            class="public-nav-toggle"
            type="button"
            data-public-nav-toggle
            aria-controls="public-navigation"
            aria-expanded="false"
        >
            <span class="sr-only">Open navigation</span>
            <span class="public-nav-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>

        <div class="public-navigation" id="public-navigation" data-public-nav-menu>
            <div class="public-navigation-top">
                <span class="public-navigation-title">Explore Atlas</span>
                <button class="public-navigation-close" type="button" data-public-nav-close>
                    <span class="sr-only">Close navigation</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="public-primary-navigation" aria-label="Primary navigation">
                <div class="public-product-menu" data-public-product-menu>
                    <button
                        class="public-nav-link public-product-toggle {{ $isProductActive ? 'is-active' : '' }}"
                        type="button"
                        data-public-product-toggle
                        aria-controls="public-product-panel"
                        aria-expanded="false"
                    >
                        Product
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>
                    </button>

                    <div class="public-product-panel" id="public-product-panel" data-public-product-panel aria-hidden="true" inert>
                        <div class="public-product-panel-intro">
                            <span class="public-footer-eyebrow">One connected ecosystem</span>
                            <strong>Built around every fitness journey.</strong>
                            <p>Explore the experiences for members, trainers and gym teams.</p>
                        </div>
                        <div class="public-product-links">
                            @foreach ($productLinks as $link)
                                <a href="{{ $routeHref($link['route'], $link['fallback']) }}" class="public-product-link">
                                    <span class="public-product-link-icon public-product-link-icon-{{ $link['icon'] }}" aria-hidden="true"></span>
                                    <span>
                                        <strong>{{ $link['label'] }}</strong>
                                        <small>{{ $link['description'] }}</small>
                                    </span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <a class="public-nav-link {{ request()->routeIs('public.gyms.*') ? 'is-active' : '' }}" href="{{ route('public.gyms.index') }}" @if (request()->routeIs('public.gyms.*')) aria-current="page" @endif>Find Gyms</a>
                <a class="public-nav-link {{ request()->routeIs('public.pricing') ? 'is-active' : '' }}" href="{{ route('public.pricing') }}" @if (request()->routeIs('public.pricing')) aria-current="page" @endif>Pricing</a>
                <a class="public-nav-link {{ request()->routeIs('public.faq') ? 'is-active' : '' }}" href="{{ $routeHref('public.faq', 'public.contact') }}" @if (request()->routeIs('public.faq')) aria-current="page" @endif>Resources</a>
                <a class="public-nav-link {{ request()->routeIs('public.about') ? 'is-active' : '' }}" href="{{ route('public.about') }}" @if (request()->routeIs('public.about')) aria-current="page" @endif>Company</a>
            </nav>

            <div class="public-nav-actions">
                <a href="{{ route('web.gym.login') }}" class="public-button public-button-secondary">Gym Login</a>
                <a href="{{ route('public.for-gyms') }}" class="public-button public-button-primary">Get Started</a>
            </div>
        </div>
    </div>
    <button class="public-navigation-backdrop" type="button" data-public-nav-backdrop tabindex="-1" aria-label="Close navigation"></button>
</header>
