@php
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.public_whatsapp.number', '+917451008842')) ?: '917451008842';
    $whatsappUrl = 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode('Hello Atlas, I would like help choosing the right app or access path.');
    $surfaces = [
        [
            'number' => '01',
            'eyebrow' => 'Public discovery',
            'title' => 'Turn local interest into a structured gym journey.',
            'copy' => 'People can search published gyms, compare the information each gym chooses to share, open locations in maps, save favourites in the Member App, and submit a trial request for gym follow-up.',
            'points' => ['Search by name, locality and facilities', 'Published branches, plans and trainer profiles', 'Gym-managed trial request workflow'],
        ],
        [
            'number' => '02',
            'eyebrow' => 'Member App',
            'title' => 'Keep membership, training and progress in one member view.',
            'copy' => 'Members can use Atlas independently for personal workouts, diet and progress tracking. When connected to a gym, the same app can also show available membership, attendance and assigned-trainer context.',
            'points' => ['Independent workout, diet and progress tools', 'Optional gym-connected membership context', 'Workout history, notifications and supported chat'],
        ],
        [
            'number' => '03',
            'eyebrow' => 'Trainer App',
            'title' => 'Give coaches the context to act, not another disconnected inbox.',
            'copy' => 'Trainers can create an independent Atlas account. Account verification is required before a trainer can add members or manage workout and diet plans. Trainers can also use Atlas through a connected gym.',
            'points' => ['Independent access after account verification', 'Verified trainers can add members and manage plans', 'Optional gym-connected clients, context and workflows'],
        ],
        [
            'number' => '04',
            'eyebrow' => 'Gym management',
            'title' => 'Continue the relationship after discovery.',
            'copy' => 'Gym teams manage the operating record behind the experience: people, branches, memberships, attendance, recorded payments and dues, trial requests, announcements, reports and public listing controls.',
            'points' => ['Membership and branch operations', 'Attendance, billing records and trial workflow', 'Reports, permissions, activity and audit visibility'],
        ],
    ];

    $workflows = [
        ['title' => 'Discover', 'copy' => 'A visitor explores gym-published profiles and submits a trial request.'],
        ['title' => 'Connect', 'copy' => 'The gym reviews the request and brings the member relationship into its operating workflow.'],
        ['title' => 'Coach', 'copy' => 'A trainer assigns plans, follows context and communicates with the member.'],
        ['title' => 'Progress', 'copy' => 'The member logs training, meals and body progress while the gym retains operational visibility.'],
    ];
@endphp

<x-public.layouts.app page-title="Atlas Product" page-description="See how Atlas connects gym discovery, member progress, trainer coaching and gym operations.">
    <div class="atlas-product-story">
    <section class="atlas-product-hero relative overflow-hidden bg-slate-950 py-20 text-white sm:py-24 lg:py-32">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_10%,rgba(70,95,255,0.34),transparent_30rem),radial-gradient(circle_at_90%_70%,rgba(34,211,238,0.14),transparent_26rem)]" aria-hidden="true"></div>
            <div class="public-container relative grid items-center gap-14 lg:grid-cols-[1.02fr_0.98fr]">
                <div>
                    <p class="mb-5 text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">One connected Atlas ecosystem</p>
                    <h1 class="max-w-4xl text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-white sm:text-5xl lg:text-7xl">From finding a gym to managing every member journey.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">Atlas connects public gym discovery, the Member App, the Trainer App and gym operations. Each role gets a focused workspace while the gym relationship stays connected.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="#explore-by-role" class="public-button public-button-primary">Explore every role</a>
                        <a href="{{ route('public.gyms.index') }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Find gyms</a>
                    </div>
                </div>

                <div class="atlas-product-devices grid grid-cols-2 gap-3 sm:gap-5" aria-label="Atlas Member and Trainer product previews">
                    <figure class="public-media-frame mt-10 rotate-[-2deg] bg-white/5 p-2 sm:p-3">
                        <img src="{{ asset('images/product/member/dashboard-720.webp') }}" alt="Atlas Member dashboard showing connected training and membership information." class="public-app-shot rounded-[1.1rem]" width="720" height="1280" fetchpriority="high">
                    </figure>
                    <figure class="public-media-frame rotate-[2deg] bg-white/5 p-2 sm:p-3">
                        <img src="{{ asset('images/product/trainer/dashboard-720.webp') }}" alt="Atlas Trainer dashboard showing the coach's daily workspace." class="public-app-shot rounded-[1.1rem]" width="720" height="1280">
                    </figure>
                </div>
            </div>
        </section>

        <section class="atlas-network-editorial" aria-labelledby="atlas-network-title">
            <div class="public-container-wide">
                <figure class="atlas-network-figure">
                    <img
                        src="{{ asset('images/public-site/editorial/gym-operations-team.webp') }}"
                        width="1800"
                        height="900"
                        alt="A gym operations team using Atlas to coordinate the member journey."
                        loading="eager"
                        decoding="async"
                    >
                    <figcaption class="atlas-network-caption">
                        <p class="public-eyebrow">Designed as a network</p>
                        <h2 id="atlas-network-title">Three focused roles. Flexible ways to begin.</h2>
                        <p>Members can start independently. Verified trainers can work independently or through a connected gym. Gym teams retain their own permission-aware operations workspace.</p>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="atlas-product-flow public-section bg-white" aria-labelledby="workflow-heading">
            <div class="public-container">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">The connected journey</p>
                    <h2 id="workflow-heading">One relationship, four coordinated stages.</h2>
                    <p class="mt-5">Atlas keeps the hand-offs visible from initial discovery through coaching and membership operations. Trial submissions are requests reviewed by the gym—not instant bookings.</p>
                </div>
                <ol class="atlas-flow-rail mt-12 grid gap-4 md:grid-cols-4">
                    @foreach ($workflows as $workflow)
                        <li class="atlas-flow-stop public-surface-premium relative p-6">
                            <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">{{ $loop->iteration }}</span>
                            <h3 class="text-xl font-semibold text-slate-950">{{ $workflow['title'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $workflow['copy'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="explore-by-role" class="atlas-role-section public-section bg-slate-50" aria-labelledby="surfaces-heading">
            <div class="public-container">
                <div class="public-section-heading mx-auto text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Explore by role</p>
                    <h2 id="surfaces-heading">A clear workspace for everyone in the ecosystem.</h2>
                    <p class="mt-5">The product is connected, but independent access remains available. Members can begin on their own, while trainers complete account verification before adding members or managing plans.</p>
                </div>

                <div class="atlas-role-mosaic mt-14">
                    @foreach ($surfaces as $surface)
                        <article class="atlas-role-panel public-surface-premium grid gap-7 p-6 sm:p-8 lg:grid-cols-[10rem_1fr_1fr] lg:items-start lg:p-10">
                            <div>
                                <span class="text-4xl font-bold tracking-[-0.04em] text-brand-500">{{ $surface['number'] }}</span>
                                <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $surface['eyebrow'] }}</p>
                            </div>
                            <div>
                                <h3 class="text-2xl font-semibold leading-tight tracking-[-0.025em] text-slate-950 sm:text-3xl">{{ $surface['title'] }}</h3>
                                <p class="mt-4 leading-7 text-slate-600">{{ $surface['copy'] }}</p>
                            </div>
                            <ul class="space-y-3" aria-label="{{ $surface['eyebrow'] }} highlights">
                                @foreach ($surface['points'] as $point)
                                    <li class="flex gap-3 text-sm leading-6 text-slate-700">
                                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-cyan-400" aria-hidden="true"></span>
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="atlas-coaching-loop public-section bg-white">
            <div class="public-container grid items-center gap-12 lg:grid-cols-2">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Connected coaching loop</p>
                    <h2>The plan, the work and the follow-up stay in context.</h2>
                    <p class="mt-5">A verified independent trainer can add members and manage plans, or a gym can connect a trainer with assigned members. The member follows and logs the work while progress, notes and messages inform the next follow-up.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ url('/member-app') }}" class="public-button public-button-primary">Explore Member App</a>
                        <a href="{{ url('/trainer-app') }}" class="public-button public-button-secondary">Explore Trainer App</a>
                    </div>
                </div>
                <div class="atlas-loop-devices grid grid-cols-2 gap-4">
                    <figure class="public-media-frame p-2">
                        <img src="{{ asset('images/product/member/workouts-720.webp') }}" alt="Member workout plans in the Atlas Member App." class="public-app-shot rounded-2xl" width="720" height="1280" loading="lazy" decoding="async">
                    </figure>
                    <figure class="public-media-frame mt-8 p-2">
                        <img src="{{ asset('images/product/trainer/workout-builder-720.webp') }}" alt="Workout plan builder in the Atlas Trainer App." class="public-app-shot rounded-2xl" width="720" height="1280" loading="lazy" decoding="async">
                    </figure>
                </div>
            </div>
        </section>

        <section class="public-section-compact bg-white">
            <div class="public-container public-surface-dark relative overflow-hidden px-6 py-12 text-center sm:px-12 sm:py-16">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Choose your next step</p>
                <h2 class="mx-auto mt-4 max-w-3xl text-3xl font-bold tracking-[-0.035em] text-white sm:text-5xl">Explore Atlas as a member, trainer or gym team.</h2>
                <p class="mx-auto mt-5 max-w-2xl leading-7 text-slate-300">Members can start independently. Trainers can ask about account verification or a gym-connected path, and gym teams can discuss onboarding.</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('public.gyms.index') }}" class="public-button public-button-primary">Find gyms</a>
                    <a href="{{ route('public.contact') }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Contact Atlas</a>
                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="public-button border border-emerald-300/40 bg-emerald-400/15 text-white hover:bg-emerald-400/25">WhatsApp Atlas</a>
                </div>
            </div>
        </section>
    </div>
</x-public.layouts.app>
