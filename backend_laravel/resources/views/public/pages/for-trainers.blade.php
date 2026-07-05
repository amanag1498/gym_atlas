@php
    $painPoints = [
        ['title' => 'Plans get lost', 'copy' => 'Programming ends up in screenshots, chat threads, or memory instead of a stable workflow.'],
        ['title' => 'Progress is scattered', 'copy' => 'Attendance, workouts, and notes are hard to review together at the right time.'],
        ['title' => 'Follow-ups stay manual', 'copy' => 'Momentum drops when reminders depend on memory and ad hoc note apps.'],
        ['title' => 'No clear daily view', 'copy' => 'Trainers often lack a clean surface for which clients need intervention today.'],
    ];

    $features = [
        'Assigned members',
        'Workout plans',
        'Client progress',
        'Progress photos',
        'Trainer notes',
        'Follow-up reminders',
        'Trainer profile',
        'Future online coaching',
    ];

    $workflow = [
        ['title' => 'Gym assigns member', 'copy' => 'Coaching starts with real member context.'],
        ['title' => 'Trainer creates plan', 'copy' => 'Programming becomes part of the same fitness loop.'],
        ['title' => 'Member logs activity', 'copy' => 'Progress signals become visible instead of informal.'],
        ['title' => 'Trainer reviews progress', 'copy' => 'Follow-up happens with better timing and context.'],
    ];

    $formRedirect = route('public.for-trainers').'#trainer-access';
@endphp

<x-public.layouts.app page-title="For Trainers" page-description="A premium trainer experience for assigned members, workout plans, progress tracking, follow-ups, and future coaching growth.">
    <section class="hero-wrap" style="background-image: linear-gradient(110deg, rgba(10,17,32,0.58) 0%, rgba(20,58,138,0.22) 44%, rgba(255,255,255,0.04) 100%), url('https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=1800&q=80'); min-height: 88vh; background-position: center;">
        <div class="overlay" style="background: transparent !important; opacity: 1 !important;"></div>
        <div class="container">
            <div class="row no-gutters align-items-center" style="min-height: 88vh; padding-top: 6rem; padding-bottom: 4rem;">
                <div class="col-xl-8 col-lg-9 ftco-animate public-reveal">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">For trainers</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(3rem, 6vw, 5.35rem); font-weight: 800; line-height: 0.98;">Coach with context, not scattered screenshots.</h1>
                    <p class="mt-4 atlas-hero-copy public-reveal public-reveal-delay-1">
                        Atlas gives trainers a cleaner workspace for assigned members, workout plans, progress notes, reminders, and future online coaching inside the same gym ecosystem.
                    </p>
                    <div class="mt-4 d-flex flex-wrap public-reveal public-reveal-delay-2" style="gap: 0.9rem;">
                        <a href="#trainer-access" class="btn btn-primary p-3 px-4">Request Trainer Access</a>
                        <a href="#trainer-access" class="btn btn-white btn-outline-white p-3 px-4">Join Through Your Gym</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Coaching friction</h3>
                        <h2 class="mb-2">Most trainers do the right work in the wrong places.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="atlas-lead mb-0">
                        Trainer workflows become stronger when programming, member context, progress, and follow-ups stay connected to the gym’s operating layer.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($painPoints as $point)
                    <div class="col-md-6 col-xl-3 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index > 1 ? 'public-reveal-delay-2' : '') }}">
                        <div class="atlas-card p-4 h-100 mb-4">
                            <span class="public-pill">0{{ $loop->iteration }}</span>
                            <h3 class="mt-4" style="font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.16;">{{ $point['title'] }}</h3>
                            <p class="mt-3 mb-0">{{ $point['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section">
        <div class="container">
            <div class="row justify-content-center mb-5 pb-3">
                <div class="col-lg-8 heading-section ftco-animate text-center public-reveal">
                    <h3 class="subheading">Feature set</h3>
                    <h2 class="mb-3">A coaching workspace, not an afterthought</h2>
                    <p class="atlas-lead mb-0">Designed for the real daily rhythm of trainers working with gym-assigned members.</p>
                </div>
            </div>
            <div class="row">
                @foreach ($features as $feature)
                    <div class="col-md-6 col-lg-3 ftco-animate public-reveal {{ $loop->index % 4 === 1 ? 'public-reveal-delay-1' : ($loop->index % 4 > 1 ? 'public-reveal-delay-2' : '') }}">
                        <div class="atlas-card p-4 mb-4 h-100">
                            <div class="public-kicker mb-3">Trainer tool</div>
                            <h3 style="font-size: 1.18rem; font-weight: 800; color: #0f172a; line-height: 1.15;">{{ $feature }}</h3>
                            <p class="mt-3 mb-0">Built to stay attached to the member relationship instead of a separate note or chat thread.</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Workflow</h3>
                        <h2 class="mb-2">From assigned member to better follow-up</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="atlas-lead mb-0">The trainer layer becomes useful when it is connected to the same member lifecycle the gym already runs.</p>
                </div>
            </div>
            <div class="row">
                @foreach ($workflow as $step)
                    <div class="col-lg-3 ftco-animate public-reveal {{ $loop->index > 0 ? 'public-reveal-delay-1' : '' }}">
                        <div class="atlas-card p-4 h-100 mb-4">
                            <span class="public-pill">0{{ $loop->iteration }}</span>
                            <h3 class="mt-4" style="font-size: 1.18rem; font-weight: 800; color: #0f172a; line-height: 1.16;">{{ $step['title'] }}</h3>
                            <p class="mt-3 mb-0">{{ $step['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="trainer-access" class="ftco-section">
        <div class="container">
            <div class="row align-items-start">
                <div class="col-lg-4 ftco-animate public-reveal mb-4 mb-lg-0">
                    <div class="atlas-card p-4 p-md-5 h-100">
                        <div class="public-kicker mb-3">Access flow</div>
                        <h2 class="mb-4" style="font-size: 1.8rem; font-weight: 800; line-height: 1.08; color: #0f172a;">Request access through the real trainer inquiry path.</h2>
                        <div style="display: grid; gap: 1rem;">
                            @foreach ([
                                ['title' => 'Real request', 'copy' => 'This page uses the actual trainer inquiry flow.'],
                                ['title' => 'Structured routing', 'copy' => 'Your request reaches the team as a trainer inquiry.'],
                                ['title' => 'Gym-linked', 'copy' => 'Your gym can then connect your trainer access in the platform.'],
                            ] as $item)
                                <div>
                                    <div class="public-kicker mb-1">{{ $item['title'] }}</div>
                                    <p class="mb-0">{{ $item['copy'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    @if (session('success'))
                        <div class="mb-4 px-4 py-4 atlas-alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 px-4 py-4 atlas-alert-danger">
                            <div style="font-weight: 800;">Please correct the highlighted trainer inquiry fields.</div>
                            <ul class="mt-3 mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="atlas-card p-4 p-md-5">
                        <div class="public-kicker mb-3">Trainer inquiry</div>
                        <h3 style="font-size: 1.9rem; font-weight: 800; color: #0f172a; line-height: 1.08;">Start the trainer access conversation.</h3>

                        <form method="POST" action="{{ route('public.contact.store') }}" class="contact-form mt-4">
                            @csrf
                            <input type="hidden" name="inquiry_type" value="trainer">
                            <input type="hidden" name="redirect_to" value="{{ $formRedirect }}">

                            <div class="form-group">
                                <input id="trainer_name" name="name" value="{{ old('name') }}" class="form-control" placeholder="Trainer name">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="trainer_email" name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="Email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input id="trainer_phone" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="Phone">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea id="trainer_message" name="message" rows="5" class="form-control" placeholder="Tell us about your gym connection">{{ old('message', 'I want trainer access through my gym on the platform.') }}</textarea>
                            </div>
                            <div class="form-group mb-0">
                                <input type="submit" value="Submit Trainer Inquiry" class="btn btn-primary py-3 px-5">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
