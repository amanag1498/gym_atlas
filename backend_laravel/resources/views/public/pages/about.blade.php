<x-public.layouts.app page-title="About" page-description="About the connected fitness ecosystem for gyms, trainers, and members.">
    <section class="hero-wrap hero-wrap-2" style="background-image: url('https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1800&q=80'); min-height: 34rem;">
        <div class="overlay"></div>
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 34rem; padding-top: 8rem; padding-bottom: 4.5rem;">
                <div class="col-xl-8 col-lg-10 ftco-animate">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">About Atlas</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(3rem, 6vw, 5.4rem); line-height: 0.98;">A cleaner operating layer for the fitness ecosystem.</h1>
                    <p class="atlas-hero-copy mb-0">
                        Atlas connects discovery, gym operations, trainer workflow, and member continuity so the journey does not break after the first inquiry.
                    </p>
                    <p class="breadcrumbs mt-4 mb-0"><span class="mr-2"><a href="{{ route('public.home') }}">Home</a></span> <span>About</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Why it exists</h3>
                        <h2 class="mb-4">Fitness businesses should not run discovery, members, collections, trainers, and follow-ups in disconnected tools.</h2>
                        <p class="atlas-lead mb-0">
                            Atlas is built around one connected loop: people find a gym, gyms receive structured demand, members continue through plans and attendance, and trainers coach with better context.
                        </p>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 mt-5 mt-lg-0 ftco-animate public-reveal public-reveal-delay-1">
                    <div class="atlas-dark-panel p-4 p-md-5 atlas-float">
                        <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Platform belief</div>
                        <h3 class="text-white mb-3" style="font-size: 1.85rem; font-weight: 800; line-height: 1.08;">The best gym experience starts before the first visit.</h3>
                        <p class="mb-0">
                            Public pages should create trust, operations should capture intent, and coaching should keep the relationship moving after onboarding.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 heading-section text-center ftco-animate public-reveal">
                    <h3 class="subheading">Core ideas</h3>
                    <h2 class="mb-3">What the platform is built to solve</h2>
                    <p class="atlas-lead mb-0">Every public page and authenticated workflow is designed to move the same relationship forward.</p>
                </div>
            </div>
            <div class="row">
                @foreach ([
                    ['title' => 'Connected discovery', 'copy' => 'Public profiles guide members into meaningful next actions instead of ending at static listing pages.', 'tag' => 'Discovery'],
                    ['title' => 'Operational control', 'copy' => 'Gyms get one place for dues, memberships, attendance, branches, trial leads, and daily visibility.', 'tag' => 'Operations'],
                    ['title' => 'Trainer continuity', 'copy' => 'Coaching works better when trainer workflows stay attached to actual members and gym context.', 'tag' => 'Coaching'],
                ] as $item)
                    <div class="col-lg-4 d-flex ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div class="atlas-card p-4 p-md-5 w-100 mb-4">
                            <div class="public-kicker mb-3">{{ $item['tag'] }}</div>
                            <h3 style="font-size: 1.42rem; font-weight: 800; line-height: 1.1; color: #0f172a;">{{ $item['title'] }}</h3>
                            <p class="mt-3 mb-0">{{ $item['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 heading-section text-center ftco-animate public-reveal">
                    <h3 class="subheading">Operating loop</h3>
                    <h2 class="mb-3">One platform, three connected audiences</h2>
                </div>
            </div>
            <div class="row">
                @foreach ([
                    ['value' => '01', 'label' => 'Members discover better-fit gyms with clearer signals.'],
                    ['value' => '02', 'label' => 'Gyms turn interest into structured trials, leads, and operations.'],
                    ['value' => '03', 'label' => 'Trainers coach with member context, not disconnected screenshots.'],
                    ['value' => '04', 'label' => 'Owners see the full story from discovery to retention.'],
                ] as $stat)
                    <div class="col-md-6 col-xl-3 ftco-animate public-reveal {{ $loop->index > 0 ? 'public-reveal-delay-1' : '' }}">
                        <div class="atlas-card p-4 mb-4 h-100">
                            <div style="font-size: 2.25rem; font-weight: 800; color: #2563eb; line-height: 1;">{{ $stat['value'] }}</div>
                            <p class="mt-3 mb-0">{{ $stat['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-public.layouts.app>
