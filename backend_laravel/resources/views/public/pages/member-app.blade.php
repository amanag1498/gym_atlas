@php
    $capabilityGroups = [
        [
            'eyebrow' => 'Personal fitness, from day one',
            'title' => 'Start training without waiting for a gym.',
            'copy' => 'Use the Member App individually to create personal workout and diet plans, record sessions and meals, and build a private progress history. A gym connection is optional and can be added later.',
            'items' => ['Personal workout and diet plans', 'Session and meal records', 'Weight, measurements and progress photos'],
        ],
        [
            'eyebrow' => 'Connect a gym when you want',
            'title' => 'Add gym and coaching context later.',
            'copy' => 'Connecting with a gym unlocks the gym-scoped experience: membership and fee visibility, attendance, branch context, trainer assignments, invitations, and the features your gym makes available.',
            'items' => ['Membership and fee visibility', 'Attendance and branch context', 'Trainer assignment and gym invitations'],
        ],
        [
            'eyebrow' => 'Training',
            'title' => 'Follow the plan and capture the work.',
            'copy' => 'Create and duplicate personal plans or start from the workout library without a gym. If you connect later, you can also use trainer-assigned plans. During a session, record exercises, sets, reps and weight, then return to history, volume trends and personal records.',
            'items' => ['Assigned and personal workout plans', 'Guided session logging', 'History, volume and personal records'],
        ],
        [
            'eyebrow' => 'Diet and progress',
            'title' => 'Make everyday progress visible.',
            'copy' => 'Create personal meal-based plans with flexible meal names, use templates and mark meals complete. A connected gym or trainer can also assign plans. Add weight, body measurements and progress photos over time.',
            'items' => ['Assigned, personal and template diets', 'Meal completion records', 'Weight, measurements and progress photos'],
        ],
        [
            'eyebrow' => 'Coaching and updates',
            'title' => 'Keep your trainer and next action close.',
            'copy' => 'See your assigned trainer, exchange persistent messages with history and read state, and use report or block safety controls. Live delivery updates depend on the realtime service configured for the deployment.',
            'items' => ['Assigned trainer context', 'Realtime chat and safety controls', 'Notifications and category preferences'],
        ],
    ];
@endphp

<x-public.layouts.app page-title="Atlas Member App" page-description="Use the Atlas Member App independently for personal workouts, diet plans and progress, then connect a gym for memberships, attendance, trainer assignments and gym-scoped features.">
    <div class="atlas-app-story atlas-member-story">
    <section class="atlas-app-hero relative overflow-hidden bg-slate-950 py-20 text-white sm:py-24 lg:py-32">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_12%_20%,rgba(70,95,255,0.38),transparent_30rem),radial-gradient(circle_at_90%_78%,rgba(34,211,238,0.13),transparent_25rem)]" aria-hidden="true"></div>
            <div class="public-container relative grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <p class="mb-5 text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Atlas Member App</p>
                    <h1 class="max-w-3xl text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-white sm:text-5xl lg:text-7xl">Your training and progress, with or without a gym.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">Start individually with personal workouts, diet plans and progress tracking. Connect a gym later to add membership, attendance, trainer assignments and gym-scoped features without losing your history.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="#member-features" class="public-button public-button-primary">Explore individual features</a>
                        <a href="#member-features" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">See every feature</a>
                    </div>
                    <p class="mt-5 text-sm leading-6 text-slate-400">App-store availability and download links are shown only after the live listings are verified.</p>
                </div>
                <figure class="atlas-app-hero-device mx-auto w-full max-w-[28rem] public-media-frame bg-white/5 p-3 sm:p-4">
                    <img src="{{ asset('images/product/member/dashboard-720.webp') }}" alt="Atlas Member dashboard with membership and fitness information." class="public-app-shot rounded-[1.4rem]" width="720" height="1280" fetchpriority="high">
                </figure>
            </div>
        </section>

        <section class="atlas-app-overview public-section bg-white" aria-labelledby="member-overview-heading">
            <div class="public-container">
                <div class="public-section-heading mx-auto text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">A useful home for your fitness record</p>
                    <h2 id="member-overview-heading">Understand today. See the history behind it.</h2>
                    <p class="mt-5">The Member App works as a personal fitness space first. Build workouts and diet plans and record progress independently, then add gym-connected workflows whenever they become relevant.</p>
                </div>
                <div class="atlas-app-pillars mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['title' => 'Your choice', 'copy' => 'Use Atlas independently or connect with a gym later.'],
                        ['title' => 'Training', 'copy' => 'Plans, sessions, exercise history, volume and records.'],
                        ['title' => 'Diet', 'copy' => 'Flexible meal-based plans, templates and meal logs.'],
                        ['title' => 'Progress', 'copy' => 'Weight, measurements, photos and connected summaries.'],
                    ] as $item)
                        <article class="atlas-app-pillar public-surface-premium p-6">
                            <span class="text-sm font-bold text-brand-600">0{{ $loop->iteration }}</span>
                            <h3 class="mt-5 text-xl font-semibold text-slate-950">{{ $item['title'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $item['copy'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="atlas-screen-gallery public-section bg-slate-50" aria-labelledby="screens-heading">
            <div class="public-container">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Real product views</p>
                    <h2 id="screens-heading">The actions that turn a plan into a history.</h2>
                    <p class="mt-5">These product screens illustrate the actual Member App workflow. Interface details may evolve as the app is updated.</p>
                </div>
                <div class="atlas-screen-stage mt-12 grid gap-6 md:grid-cols-3">
                    @foreach ([
                        ['image' => 'activity-720.webp', 'title' => 'Daily activity', 'alt' => 'Atlas Member activity screen showing current fitness activity.'],
                        ['image' => 'workouts-720.webp', 'title' => 'Workout plans', 'alt' => 'Atlas Member workout screen showing available plans.'],
                        ['image' => 'workout-history-720.webp', 'title' => 'Training history', 'alt' => 'Atlas Member workout history screen showing completed sessions.'],
                    ] as $screen)
                        <figure class="atlas-screen-card public-surface-premium overflow-hidden p-3">
                            <div class="public-media-frame border-0 shadow-none">
                                <img src="{{ asset('images/product/member/'.$screen['image']) }}" alt="{{ $screen['alt'] }}" class="public-app-shot" width="720" height="1280" loading="lazy" decoding="async">
                            </div>
                            <figcaption class="px-3 pb-2 pt-5 text-center text-sm font-semibold text-slate-800">{{ $screen['title'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="member-features" class="atlas-capability-ledger public-section bg-white" aria-labelledby="member-features-heading">
            <div class="public-container">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Detailed capabilities</p>
                    <h2 id="member-features-heading">Everything the current Member App helps you manage.</h2>
                    <p class="mt-5">Personal workouts, diet plans and progress do not require a gym. Membership, attendance, trainer assignment and other gym-scoped features become available when you connect with a gym.</p>
                </div>
                <div class="mt-12 divide-y divide-slate-200 border-y border-slate-200">
                    @foreach ($capabilityGroups as $group)
                        <article class="atlas-capability-row grid gap-6 py-9 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:py-12">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">{{ $group['eyebrow'] }}</p>
                                <h3 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-slate-950 sm:text-3xl">{{ $group['title'] }}</h3>
                                <p class="mt-4 leading-7 text-slate-600">{{ $group['copy'] }}</p>
                            </div>
                            <ul class="grid content-start gap-3 sm:grid-cols-3 lg:grid-cols-1">
                                @foreach ($group['items'] as $item)
                                    <li class="public-surface-premium flex gap-3 p-4 text-sm font-medium leading-6 text-slate-700">
                                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-cyan-400" aria-hidden="true"></span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="public-section-compact bg-slate-50" aria-labelledby="member-boundaries-heading">
            <div class="public-container grid gap-6 lg:grid-cols-2">
                <div class="public-surface-premium p-7 sm:p-9">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brand-600">Supported where configured</p>
                    <h2 id="member-boundaries-heading" class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-slate-950">Your personal tools travel with you.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Creating workouts and diets and tracking progress remain available for individual use. Biometric-assisted attendance depends on a connected gym’s hardware and workflow. Android step trends depend on a supported device, health services and permissions.</p>
                </div>
                <div class="rounded-[1.35rem] border border-amber-200 bg-amber-50 p-7 sm:p-9">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-700">Clear product boundary</p>
                    <h3 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-slate-950">Recorded payment visibility is not in-app checkout.</h3>
                    <p class="mt-4 leading-7 text-slate-700">Members can see amounts and dues recorded by their gym. Atlas does not currently promise member self-checkout, autopay, live classes, video coaching, or AI-generated plans.</p>
                </div>
            </div>
        </section>

        <section class="public-section-compact bg-white">
            <div class="public-container public-surface-dark px-6 py-12 text-center sm:px-12 sm:py-16">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Connect when it adds value</p>
                <h2 class="mx-auto mt-4 max-w-3xl text-3xl font-bold tracking-[-0.035em] text-white sm:text-5xl">Train independently now. Find a gym when you are ready.</h2>
                <p class="mx-auto mt-5 max-w-2xl leading-7 text-slate-300">Your personal training, diet and progress records can start before a gym connection. Later, explore published facilities, branches, trainers and plans, then send a trial request for the gym to review.</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('public.gyms.index') }}" class="public-button public-button-primary">Explore gyms</a>
                    <a href="{{ route('public.contact') }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Ask Atlas</a>
                </div>
            </div>
        </section>
    </div>
</x-public.layouts.app>
