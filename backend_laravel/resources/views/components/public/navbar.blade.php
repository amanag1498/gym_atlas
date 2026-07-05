@php
    $current = request()->route()?->getName();
    $links = [
        ['label' => 'Home', 'route' => 'public.home'],
        ['label' => 'Find Gyms', 'route' => 'public.gyms.index'],
        ['label' => 'For Gyms', 'route' => 'public.for-gyms'],
        ['label' => 'For Trainers', 'route' => 'public.for-trainers'],
        ['label' => 'Pricing', 'route' => 'public.pricing'],
        ['label' => 'About', 'route' => 'public.about'],
        ['label' => 'Contact', 'route' => 'public.contact'],
    ];

    $actions = [
        ['label' => 'Gym Login', 'route' => 'web.gym.login', 'class' => 'public-nav-action-secondary'],
        ['label' => 'List Your Gym', 'route' => 'public.for-gyms', 'class' => 'public-nav-action-primary'],
    ];

    if (auth()->check() && auth()->user()->hasRole('platform_admin')) {
        $actions[] = ['label' => 'Admin', 'route' => 'web.admin.login', 'class' => 'public-nav-action-secondary'];
    }
@endphp

<nav class="navbar navbar-expand-lg navbar-dark ftco_navbar ftco-navbar-light" id="ftco-navbar">
    <div class="container">
        <a class="navbar-brand" href="{{ route('public.home') }}">
            <span class="public-brand-mark mr-2"></span>ATLAS
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#ftco-nav" aria-controls="ftco-nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="oi oi-menu"></span> Menu
        </button>

        <div class="public-nav-actions public-nav-actions-desktop">
            @foreach ($actions as $action)
                <a href="{{ route($action['route']) }}" class="{{ $action['class'] }}">{{ $action['label'] }}</a>
            @endforeach
        </div>

        <div class="collapse navbar-collapse" id="ftco-nav">
            <ul class="navbar-nav ml-auto">
                @foreach ($links as $link)
                    <li class="nav-item {{ $current === $link['route'] ? 'active' : '' }}">
                        <a href="{{ route($link['route']) }}" class="nav-link">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="public-nav-actions public-nav-actions-mobile">
                @foreach ($actions as $action)
                    <a href="{{ route($action['route']) }}" class="{{ $action['class'] }}">{{ $action['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
</nav>
