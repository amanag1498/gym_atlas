@php
    $footerRoute = static fn (string $route, string $fallback): string => Route::has($route)
        ? route($route)
        : route($fallback);

    $footerGroups = [
        'Product' => [
            ['Member App', 'public.member-app', 'public.gyms.index'],
            ['Trainer App', 'public.trainer-app', 'public.for-trainers'],
            ['Gym Management', 'public.gym-management', 'public.for-gyms'],
            ['How Atlas Works', 'public.how-it-works', 'public.home'],
        ],
        'Explore' => [
            ['Find Gyms', 'public.gyms.index', 'public.gyms.index'],
            ['For Gym Owners', 'public.for-gyms', 'public.for-gyms'],
            ['For Trainers', 'public.for-trainers', 'public.for-trainers'],
            ['Pricing', 'public.pricing', 'public.pricing'],
        ],
        'Company' => [
            ['About Atlas', 'public.about', 'public.about'],
            ['Contact', 'public.contact', 'public.contact'],
            ['Help & FAQ', 'public.faq', 'public.contact'],
            ['Gym Login', 'web.gym.login', 'web.gym.login'],
        ],
        'Legal' => [
            ['Privacy Policy', 'public.privacy-policy', 'public.privacy-policy'],
            ['Terms', 'public.terms', 'public.terms'],
            ['Account Deletion', 'public.account-deletion', 'public.account-deletion'],
        ],
    ];
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.public_whatsapp.number', '917451008842'));
    $whatsappHref = 'https://wa.me/'.$whatsappNumber.'?text='.urlencode('Hello Atlas, I would like help choosing the right access path.');
@endphp

<footer class="public-footer" aria-labelledby="public-footer-heading">
    <div class="public-footer-glow" aria-hidden="true"></div>
    <div class="public-container-wide public-footer-inner">
        <section class="public-footer-contact-strip" aria-labelledby="public-footer-heading">
            <div class="public-footer-contact-copy">
                <span class="public-footer-eyebrow">Need help choosing?</span>
                <h2 id="public-footer-heading">Member, verified independent trainer, or gym access.</h2>
            </div>
            <div class="public-footer-contact-actions">
                <a href="{{ route('public.contact') }}" class="public-button public-button-primary">Send an enquiry</a>
                <a href="{{ $whatsappHref }}" class="public-button public-button-on-dark" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            </div>
        </section>

        <div class="public-footer-main">
            <div class="public-footer-brand-column">
                <a class="public-brand public-brand-footer" href="{{ route('public.home') }}" aria-label="Atlas home">
                    <img
                        class="public-brand-image"
                        src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}"
                        width="44"
                        height="44"
                        loading="lazy"
                        alt=""
                    >
                    <span class="public-brand-copy">
                        <span class="public-brand-name">ATLAS</span>
                        <span class="public-brand-tagline">Fitness, connected</span>
                    </span>
                </a>
                <p>Members and trainers can start independently, while gyms add connected operational and coaching context.</p>
                <div class="public-footer-audience-links" aria-label="Quick actions">
                    <a href="{{ route('public.gyms.index') }}">I’m a member <span aria-hidden="true">→</span></a>
                    <a href="{{ route('public.for-trainers') }}">I’m a trainer <span aria-hidden="true">→</span></a>
                    <a href="{{ route('public.for-gyms') }}">I run a gym <span aria-hidden="true">→</span></a>
                </div>
            </div>

            <nav class="public-footer-navigation" aria-label="Footer navigation">
                @foreach ($footerGroups as $heading => $links)
                    <div class="public-footer-group">
                        <h3>{{ $heading }}</h3>
                        <ul>
                            @foreach ($links as [$label, $route, $fallback])
                                <li><a href="{{ $footerRoute($route, $fallback) }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </div>

        <div class="public-footer-bottom">
            <p>© {{ date('Y') }} Atlas by Techybugs. All rights reserved.</p>
            <p class="public-footer-status"><span aria-hidden="true"></span>Built for the complete fitness ecosystem</p>
        </div>
    </div>
</footer>
