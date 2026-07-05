@php
    $inquiryType = in_array($inquiryType ?? 'user', ['user', 'gym', 'trainer', 'support'], true) ? $inquiryType : 'user';
    $supportEmail = $settings['support_email'] ?? null;
    $supportPhone = $settings['support_phone'] ?? null;
@endphp

<x-public.layouts.app page-title="Contact" page-description="Contact the fitness platform team for user, gym, trainer, or support inquiries.">
    <section class="hero-wrap hero-wrap-2" style="background-image: url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1800&q=80'); min-height: 34rem;">
        <div class="overlay"></div>
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 34rem; padding-top: 8rem; padding-bottom: 4.5rem;">
                <div class="col-xl-8 col-lg-10 ftco-animate">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Contact Atlas</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(3rem, 6vw, 5.4rem); line-height: 0.98;">Talk to the right team without the back-and-forth.</h1>
                    <p class="atlas-hero-copy mb-0">
                        Send a structured inquiry for member help, gym onboarding, trainer access, or platform support.
                    </p>
                    <p class="breadcrumbs mt-4 mb-0"><span class="mr-2"><a href="{{ route('public.home') }}">Home</a></span> <span>Contact</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light contact-section">
        <div class="container">
            @if (session('success'))
                <div class="mb-4 px-4 py-4 atlas-alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 px-4 py-4 atlas-alert-danger">
                    <div style="font-weight: 800;">Please correct the highlighted contact form fields.</div>
                    <ul class="mt-3 mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row align-items-start">
                <div class="col-lg-4 ftco-animate public-reveal mb-4 mb-lg-0">
                    <div class="atlas-card p-4 p-md-5 h-100">
                        <div class="public-kicker mb-3">Inquiry routing</div>
                        <h2 class="mb-4" style="font-size: 1.75rem; font-weight: 800; line-height: 1.1; color: #0f172a;">Contact information</h2>
                        <div style="display: grid; gap: 1rem;">
                            <div>
                                <div class="public-kicker mb-1">Types</div>
                                <p class="mb-0">User, Gym, Trainer, Support</p>
                            </div>
                            @if ($supportPhone)
                                <div>
                                    <div class="public-kicker mb-1">Phone</div>
                                    <p class="mb-0"><a class="atlas-link" href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}">{{ $supportPhone }}</a></p>
                                </div>
                            @endif
                            @if ($supportEmail)
                                <div>
                                    <div class="public-kicker mb-1">Email</div>
                                    <p class="mb-0"><a class="atlas-link" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></p>
                                </div>
                            @endif
                            <div>
                                <div class="public-kicker mb-1">Use case</div>
                                <p class="mb-0">Platform onboarding, public discovery help, and product support.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <div class="atlas-card p-4 p-md-5">
                        <div class="d-flex flex-wrap justify-content-between align-items-start mb-4" style="gap: 1rem;">
                            <div>
                                <div class="public-kicker mb-3">Send request</div>
                                <h2 class="mb-0" style="font-size: 2rem; font-weight: 800; line-height: 1.08; color: #0f172a;">Tell us what you need.</h2>
                            </div>
                            <span class="public-pill">Usually routed by inquiry type</span>
                        </div>

                        <form method="POST" action="{{ route('public.contact.store') }}" class="contact-form">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-control" placeholder="Your name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="Your email">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="form-control" placeholder="Phone number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <select id="inquiry_type" name="inquiry_type" class="form-control">
                                            @foreach (['user' => 'User', 'gym' => 'Gym', 'trainer' => 'Trainer', 'support' => 'Support'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('inquiry_type', $inquiryType) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea id="message" name="message" cols="30" rows="7" class="form-control" placeholder="Tell us what you need">{{ old('message') }}</textarea>
                            </div>
                            <div class="form-group mb-0 d-flex flex-wrap align-items-center" style="gap: 1rem;">
                                <input type="submit" value="Send Message" class="btn btn-primary py-3 px-5">
                                <span style="color: #64748b; font-size: 0.94rem;">Your message stays connected to the selected inquiry type.</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
