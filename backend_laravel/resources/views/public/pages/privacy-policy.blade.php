@php
    $supportEmail = $settings['support_email'] ?? null;
    $externalUrl = $settings['privacy_policy_url'] ?? null;
@endphp

<x-public.layouts.app page-title="Privacy Policy" page-description="Privacy principles and data handling overview for the public fitness platform.">
    <section class="hero-wrap hero-wrap-2" style="background-image: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1800&q=80'); min-height: 32rem;">
        <div class="overlay"></div>
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 32rem; padding-top: 8rem; padding-bottom: 4rem;">
                <div class="col-xl-8 col-lg-10 ftco-animate">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Privacy</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(2.8rem, 5.5vw, 5rem); line-height: 0.98;">Privacy principles for Atlas.</h1>
                    <p class="atlas-hero-copy mb-0">A simple overview of how the public fitness platform treats discovery, inquiry, and operating data.</p>
                    <p class="breadcrumbs mt-4 mb-0"><span class="mr-2"><a href="{{ route('public.home') }}">Home</a></span> <span>Privacy Policy</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 ftco-animate public-reveal">
                    <div class="atlas-card p-4 p-md-5">
                        <div class="public-kicker mb-3">Data handling</div>
                        <h2 class="mb-4" style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; line-height: 1.05; color: #0f172a;">Public visibility stays intentional. Private operations stay protected.</h2>
                        <div style="color: #475569; line-height: 1.95; font-size: 1rem; display: grid; gap: 1rem;">
                            <p class="mb-0">The platform collects only the information needed to support public gym discovery, contact requests, trial lead flow, and connected experiences across members, gyms, and trainers.</p>
                            <p class="mb-0">Public pages expose only intentionally published gym profile data. Private member, trainer, billing, and gym operations data stay outside public discovery surfaces.</p>
                            <p class="mb-0">Contact submissions are stored so the team can respond to user, gym, trainer, or support inquiries and keep follow-up context tied to the original request.</p>
                            <p class="mb-0">When gym owners manage public listing settings, the system respects those controls for pricing visibility, contact visibility, and trial availability on the public website.</p>
                            @if ($supportEmail)
                                <p class="mb-0">Privacy-related questions can be directed to <a href="mailto:{{ $supportEmail }}" class="atlas-link">{{ $supportEmail }}</a>.</p>
                            @endif
                        </div>

                        @if ($externalUrl)
                            <div class="mt-4 public-ledger px-4 py-4" style="color: #475569;">
                                The configured production privacy policy can also be viewed here:
                                <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="atlas-link">{{ $externalUrl }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
