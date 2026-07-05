@php
    $supportEmail = $settings['support_email'] ?? null;
    $externalUrl = $settings['terms_url'] ?? null;
@endphp

<x-public.layouts.app page-title="Terms" page-description="Terms overview for public use of the connected fitness platform.">
    <section class="hero-wrap hero-wrap-2" style="background-image: url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1800&q=80'); min-height: 32rem;">
        <div class="overlay"></div>
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 32rem; padding-top: 8rem; padding-bottom: 4rem;">
                <div class="col-xl-8 col-lg-10 ftco-animate">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Terms</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(2.8rem, 5.5vw, 5rem); line-height: 0.98;">Terms for using the public platform.</h1>
                    <p class="atlas-hero-copy mb-0">A clear public-use overview for discovery, inquiries, and trial request flows.</p>
                    <p class="breadcrumbs mt-4 mb-0"><span class="mr-2"><a href="{{ route('public.home') }}">Home</a></span> <span>Terms</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10 ftco-animate public-reveal">
                    <div class="atlas-card p-4 p-md-5">
                        <div class="public-kicker mb-3">Public use</div>
                        <h2 class="mb-4" style="font-size: clamp(2rem, 4vw, 3rem); font-weight: 800; line-height: 1.05; color: #0f172a;">Use Atlas accurately, lawfully, and with real intent.</h2>
                        <div style="color: #475569; line-height: 1.95; font-size: 1rem; display: grid; gap: 1rem;">
                            <p class="mb-0">The public website is intended for gym discovery, inquiries, and trial lead creation across eligible listings on the platform.</p>
                            <p class="mb-0">Gyms are responsible for the accuracy of the information they choose to publish publicly, including pricing visibility, facility details, contact visibility, and trial availability.</p>
                            <p class="mb-0">Submitting a contact or trial request does not guarantee acceptance, conversion, or immediate onboarding; those decisions stay with the relevant gym and its operations team.</p>
                            <p class="mb-0">Use of the platform should stay lawful, accurate, and non-abusive across public discovery, contact forms, and authenticated fitness workflows.</p>
                            @if ($supportEmail)
                                <p class="mb-0">Questions about these terms can be raised via <a href="mailto:{{ $supportEmail }}" class="atlas-link">{{ $supportEmail }}</a>.</p>
                            @endif
                        </div>

                        @if ($externalUrl)
                            <div class="mt-4 public-ledger px-4 py-4" style="color: #475569;">
                                The configured production terms can also be viewed here:
                                <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="atlas-link">{{ $externalUrl }}</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
