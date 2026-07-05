@php
    $gymLoginHref = Route::has('web.gym.login') ? route('web.gym.login') : null;
    $formRedirect = route('public.for-gyms').'#lead-form';

    $painPoints = [
        ['title' => 'Attendance still eats desk time', 'copy' => 'Check-ins, missed entries, and follow-up corrections still sit too close to the front desk instead of the system.'],
        ['title' => 'Collections stay fragmented', 'copy' => 'Dues, reminders, partial payments, and renewals often live across chats, sheets, and memory.'],
        ['title' => 'Retention signals surface too late', 'copy' => 'Owners usually notice churn when a membership is already slipping, not when the risk first appears.'],
        ['title' => 'Visibility and operations are disconnected', 'copy' => 'The public profile attracts interest, but the gym often cannot continue that journey in the same operating flow.'],
    ];

    $capabilityPillars = [
        [
            'title' => 'Member lifecycle',
            'copy' => 'Onboarding, plan assignment, renewals, custom fees, dues, and continuity without scattered records.',
            'points' => ['Members', 'Memberships', 'Custom fees', 'Renewals'],
        ],
        [
            'title' => 'Floor operations',
            'copy' => 'Attendance, trainer visibility, branch context, and everyday front-desk workflow in one place.',
            'points' => ['Attendance', 'Branches', 'Trainer flow', 'Staff visibility'],
        ],
        [
            'title' => 'Revenue and demand',
            'copy' => 'Payments, reminders, public listing demand, and trial conversion move through the same system.',
            'points' => ['Payments', 'Dues', 'Trial leads', 'Public listing'],
        ],
    ];

    $journeySteps = [
        [
            'step' => '01',
            'title' => 'A gym gets discovered',
            'copy' => 'A cleaner public profile helps the right members find the right branch faster.',
        ],
        [
            'step' => '02',
            'title' => 'Interest becomes structured demand',
            'copy' => 'Trials, enquiries, and contact requests move directly into an operating workflow instead of a dead-end inbox.',
        ],
        [
            'step' => '03',
            'title' => 'Operations continue in one system',
            'copy' => 'Memberships, attendance, payments, trainers, and branch activity stay connected after the first touchpoint.',
        ],
    ];

    $opsModules = [
        ['title' => 'Members and plans', 'copy' => 'Track onboarding, active plans, due members, custom pricing, and renewals.'],
        ['title' => 'Payments and dues', 'copy' => 'Keep collections, reminders, and auditability closer to actual operations.'],
        ['title' => 'Attendance and floor flow', 'copy' => 'Reduce manual gaps and keep attendance tied to live member records.'],
        ['title' => 'Trainer and branch visibility', 'copy' => 'Connect coaches and locations to the member relationship instead of separate sheets.'],
        ['title' => 'Leads and public listing', 'copy' => 'Use public discovery as an operational input, not just a marketing artifact.'],
        ['title' => 'Reports and decision support', 'copy' => 'Surface what matters faster so owners do not have to piece the story together manually.'],
    ];
@endphp

<x-public.layouts.app page-title="For Gyms" page-description="A premium operating system for gyms that connects public discovery, member lifecycle, payments, attendance, branches, trainers, and trial demand.">
    <section class="hero-wrap" style="background-image: linear-gradient(110deg, rgba(10,17,32,0.58) 0%, rgba(20,58,138,0.22) 44%, rgba(255,255,255,0.04) 100%), url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1800&q=80'); min-height: 88vh; background-position: center;">
        <div class="overlay" style="background: transparent !important; opacity: 1 !important;"></div>
        <div class="container">
            <div class="row no-gutters align-items-center" style="min-height: 88vh; padding-top: 6rem; padding-bottom: 4rem;">
                <div class="col-xl-8 col-lg-9 ftco-animate public-reveal">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">For gym operators</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(3rem, 6vw, 5.2rem); font-weight: 700; line-height: 1.02;">Run the gym from the same system that brings people in.</h1>
                    <p class="mt-4 public-reveal public-reveal-delay-1" style="max-width: 44rem; color: rgba(255,255,255,0.84); font-size: 1.04rem; line-height: 1.95;">
                        Atlas connects discovery, trial demand, memberships, attendance, payments, branches, and trainers so the gym does not have to switch tools the moment a lead becomes a real member relationship.
                    </p>
                    <div class="mt-4 d-flex flex-wrap public-reveal public-reveal-delay-2" style="gap: 0.9rem;">
                        <a href="#lead-form" class="btn btn-primary p-3 px-4">Register Your Gym</a>
                        @if ($gymLoginHref)
                            <a href="{{ $gymLoginHref }}" class="btn btn-white btn-outline-white p-3 px-4">Gym Admin Login</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light" style="background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Operational friction</h3>
                        <h2 class="mb-2">Most gyms do not lack effort. They lack one connected operating layer.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.9;">
                        The usual problem is not one missing feature. It is the break between public visibility, collections, attendance, retention, and trainer workflow. That break is where time and revenue get lost.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($painPoints as $point)
                    <div class="col-md-6 col-xl-3 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index > 1 ? 'public-reveal-delay-2' : '') }}">
                        <div style="height: 100%; padding: 1.9rem 1.6rem; border-radius: 1.45rem; background: rgba(255,255,255,0.9); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 18px 40px rgba(15,23,42,0.06);">
                            <div style="width: 2.7rem; height: 2.7rem; border-radius: 9999px; background: linear-gradient(135deg, #dbeafe, #eff6ff); display: inline-flex; align-items: center; justify-content: center; color: #2563eb; font-weight: 800;">0{{ $loop->iteration }}</div>
                            <h3 class="mt-4" style="font-size: 1.18rem; font-weight: 700; color: #0f172a; line-height: 1.2;">{{ $point['title'] }}</h3>
                            <p class="mt-3 mb-0" style="color: #475569; line-height: 1.86;">{{ $point['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section" style="background: #ffffff;">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">What changes</h3>
                        <h2 class="mb-2">Atlas gives the gym one place to continue the member story after discovery.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.92;">
                        Once a member discovers the gym, the rest should not fragment. The same system should hold their enquiry, trial, plan, attendance, payments, and coaching continuity.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($capabilityPillars as $pillar)
                    <div class="col-lg-4 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div style="height: 100%; padding: 2rem 1.75rem; border-radius: 1.55rem; background: linear-gradient(180deg, #ffffff, #f7fbff); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 20px 48px rgba(15,23,42,0.07);">
                            <div style="font-size: 0.76rem; letter-spacing: 0.16em; text-transform: uppercase; color: #2563eb; font-weight: 700;">Pillar {{ $loop->iteration }}</div>
                            <h3 class="mt-3" style="font-size: 1.42rem; font-weight: 700; color: #0f172a; line-height: 1.1;">{{ $pillar['title'] }}</h3>
                            <p class="mt-3" style="color: #475569; line-height: 1.9;">{{ $pillar['copy'] }}</p>
                            <div class="mt-4 d-flex flex-wrap" style="gap: 0.5rem;">
                                @foreach ($pillar['points'] as $point)
                                    <span class="public-pill">{{ $point }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light" style="background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">How the journey works</h3>
                        <h2 class="mb-2">Public discovery is only useful when the relationship keeps moving inside the same flow.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.9;">
                        The strongest gyms do not just capture leads. They continue those leads into operations without losing information, accountability, or timing.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($journeySteps as $step)
                    <div class="col-lg-4 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div style="height: 100%; padding: 2rem 1.75rem; border-radius: 1.45rem; background: rgba(255,255,255,0.9); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 18px 40px rgba(15,23,42,0.06);">
                            <div style="font-size: 0.78rem; letter-spacing: 0.18em; text-transform: uppercase; color: #60a5fa; font-weight: 700;">{{ $step['step'] }}</div>
                            <h3 class="mt-4" style="font-size: 1.34rem; font-weight: 700; color: #0f172a; line-height: 1.12;">{{ $step['title'] }}</h3>
                            <p class="mt-3 mb-0" style="color: #475569; line-height: 1.88;">{{ $step['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section" style="background: #ffffff;">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">What operators get</h3>
                        <h2 class="mb-2">The workflows that usually live in separate tools sit inside one operating layer.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.92;">
                        Atlas is not just a public listing page for gyms. It is the system that can continue once the member enters the funnel and the real operational work begins.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($opsModules as $module)
                    <div class="col-md-6 col-xl-4 ftco-animate public-reveal {{ $loop->index % 3 === 1 ? 'public-reveal-delay-1' : ($loop->index % 3 === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div class="mb-4" style="height: calc(100% - 1.5rem); padding: 1.9rem 1.6rem; border-radius: 1.45rem; background: rgba(255,255,255,0.92); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 18px 40px rgba(15,23,42,0.06);">
                            <h3 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; line-height: 1.15;">{{ $module['title'] }}</h3>
                            <p class="mt-3 mb-0" style="color: #475569; line-height: 1.85;">{{ $module['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="lead-form" class="ftco-section bg-light" style="background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);">
        <div class="container">
            <div class="row align-items-start">
                <div class="col-lg-5 ftco-animate public-reveal mb-4 mb-lg-0">
                    <div style="height: 100%; padding: 2.25rem 2rem; border-radius: 1.65rem; background: linear-gradient(135deg, #ffffff, #f4f9ff); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 22px 52px rgba(15,23,42,0.07);">
                        <div class="public-kicker mb-3">Next step</div>
                        <h2 style="font-size: clamp(2rem, 4vw, 2.8rem); font-weight: 700; line-height: 1.04; color: #0f172a;">Register your gym through the actual onboarding path.</h2>
                        <p class="mt-4 mb-0" style="color: #475569; line-height: 1.9;">
                            This is not a decorative lead form. It routes a real gym inquiry into the platform onboarding flow. Existing operators can use the gym login directly.
                        </p>

                        <div class="mt-5" style="display: grid; gap: 1rem;">
                            @foreach ([
                                'The team receives a structured gym inquiry.',
                                'You can describe current workflow gaps and onboarding needs.',
                                'Existing operators can skip directly to gym admin login.',
                            ] as $line)
                                <div class="d-flex align-items-start" style="gap: 0.8rem;">
                                    <div style="width: 0.55rem; height: 0.55rem; border-radius: 9999px; background: linear-gradient(135deg, #93c5fd, #2563eb); margin-top: 0.45rem;"></div>
                                    <p class="mb-0" style="color: #475569; line-height: 1.82;">{{ $line }}</p>
                                </div>
                            @endforeach
                        </div>

                        @if ($gymLoginHref)
                            <div class="mt-5">
                                <a href="{{ $gymLoginHref }}" class="btn btn-white btn-outline-white py-3 px-4">Gym Admin Login</a>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    @if (session('success'))
                        <div class="mb-4 rounded-[1rem] border border-emerald-400/20 bg-emerald-400/10 px-4 py-4 text-sm text-emerald-100">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-[1rem] border border-rose-400/20 bg-rose-400/10 px-4 py-4 text-sm text-rose-100">
                            <div class="font-semibold">Please correct the highlighted gym inquiry fields.</div>
                            <ul class="mt-3 mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div style="padding: 2rem 1.9rem; border-radius: 1.55rem; background: rgba(255,255,255,0.94); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 20px 48px rgba(15,23,42,0.07);">
                        <div class="public-kicker mb-3">Gym inquiry</div>
                        <h3 style="font-size: 1.75rem; font-weight: 700; color: #0f172a; line-height: 1.08;">Start the onboarding conversation.</h3>

                        <form method="POST" action="{{ route('public.contact.store') }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="inquiry_type" value="gym">
                            <input type="hidden" name="redirect_to" value="{{ $formRedirect }}">

                            <div class="form-group">
                                <input id="gym_name" name="name" value="{{ old('name') }}" class="form-control" placeholder="Gym or owner name">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="gym_email" name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="Email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="gym_phone" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="Phone">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea id="gym_message" name="message" rows="6" class="form-control" placeholder="Tell us about your gym, current workflow, branches, or onboarding needs">{{ old('message', 'I want to onboard my gym onto the platform and understand how Atlas can support our operations.') }}</textarea>
                            </div>
                            <div class="form-group mb-0">
                                <input type="submit" value="Submit Gym Inquiry" class="btn btn-primary py-3 px-5">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
