@php
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.public_whatsapp.number'));
    $whatsappHref = $whatsappNumber !== ''
        ? 'https://wa.me/'.$whatsappNumber.'?text='.urlencode('Hello Atlas, I would like help choosing the right Trainer App access path.')
        : null;

    $featureGroups = [
        [
            'number' => '01',
            'title' => 'Start with the right clients and context',
            'copy' => 'See today’s focused client queue and your assigned member roster. Open a member to review fitness context, attendance, workout plans, progress and workout history before following up.',
            'items' => ['Today’s client queue', 'Assigned member roster', 'Attendance, progress and logbook context'],
        ],
        [
            'number' => '02',
            'title' => 'Build repeatable coaching plans',
            'copy' => 'Create structured workout plans, build reusable templates, search the exercise library and add coaching-specific exercises. Preview and assign the right program to an eligible member.',
            'items' => ['Workout templates', 'Member plan management', 'Exercise library contribution'],
        ],
        [
            'number' => '03',
            'title' => 'Plan meals when your access permits it',
            'copy' => 'Create meal-based diet plans, maintain reusable diet templates and assign them to eligible members when the required access is available. Gym-connected trainers remain subject to gym permissions.',
            'items' => ['Access-aware diet builder', 'Reusable diet templates', 'Member assignment workflow'],
        ],
        [
            'number' => '04',
            'title' => 'Keep follow-up work visible',
            'copy' => 'Capture member notes, update them, mark work complete and review pending follow-ups. Handle trial leads assigned by the gym, invite prospective members into the approval workflow and publish trainer announcements.',
            'items' => ['Notes and pending tasks', 'Assigned trial lead updates', 'Invitations and announcements'],
        ],
        [
            'number' => '05',
            'title' => 'Keep conversations inside the coaching workspace',
            'copy' => 'Use persistent member conversations with message history and read state. Live delivery updates depend on the realtime service configured for the deployment; safety controls include reporting, blocking and unblocking.',
            'items' => ['Member inbox and threads', 'Realtime updates and read state', 'Conversation safety controls'],
        ],
    ];
@endphp

<x-public.layouts.app page-title="Atlas Trainer App" page-description="Explore the Atlas Trainer App for assigned clients, workout and diet plans, follow-ups, trial leads, messaging, and alerts.">
    <div class="atlas-app-story atlas-trainer-story">
    <section class="atlas-app-hero relative overflow-hidden bg-slate-950 py-20 text-white sm:py-24 lg:py-32">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_16%_18%,rgba(70,95,255,0.34),transparent_30rem),radial-gradient(circle_at_92%_75%,rgba(34,211,238,0.15),transparent_25rem)]" aria-hidden="true"></div>
            <div class="public-container relative grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <p class="mb-5 text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Atlas Trainer App</p>
                    <h1 class="max-w-3xl text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-white sm:text-5xl lg:text-7xl">Coach with the client context already in view.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-300">Atlas gives independent and gym-connected trainers a focused workspace for members, daily follow-ups, workout and diet plans, announcements, messaging and alerts.</p>
                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('public.contact', ['inquiry_type' => 'trainer']) }}" class="public-button public-button-primary">Request trainer access</a>
                        <a href="#trainer-features" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Explore the workspace</a>
                    </div>
                    <p class="mt-5 text-sm leading-6 text-slate-400">Every trainer can use gym-assigned features. Platform verification separately unlocks personal member invitations and plans, even while the trainer remains connected to a gym.</p>
                </div>
                <figure class="atlas-app-hero-device mx-auto w-full max-w-[28rem] public-media-frame bg-white/5 p-3 sm:p-4">
                    <img src="{{ asset('images/product/trainer/dashboard-720.webp') }}" alt="Atlas Trainer dashboard showing the daily coaching workspace." class="public-app-shot rounded-[1.4rem]" width="720" height="1280" fetchpriority="high">
                </figure>
            </div>
        </section>

        <section class="atlas-coaching-day public-section bg-white" aria-labelledby="trainer-day-heading">
            <div class="public-container grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">A clearer coaching day</p>
                    <h2 id="trainer-day-heading">See who needs attention, then act in context.</h2>
                    <p class="mt-5">Open the day with a client queue, move into a member’s plan and progress, capture the next follow-up and keep the conversation attached to the same verified trainer-member relationship.</p>
                    <ol class="mt-8 space-y-4">
                        @foreach (['Review today’s clients and alerts', 'Open the member’s training and progress context', 'Assign or update the right plan', 'Record a note, task or message'] as $step)
                            <li class="flex items-center gap-4 text-sm font-medium text-slate-700">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 font-bold text-brand-700">{{ $loop->iteration }}</span>
                                <span>{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <figure class="atlas-coaching-roster public-media-frame p-3">
                    <img src="{{ asset('images/product/trainer/clients-720.webp') }}" alt="Atlas Trainer client roster showing assigned members." class="public-app-shot rounded-[1.25rem]" width="720" height="1280" loading="lazy" decoding="async">
                </figure>
            </div>
        </section>

        <section class="atlas-screen-gallery public-section bg-slate-50" aria-labelledby="trainer-screens-heading">
            <div class="public-container">
                <div class="public-section-heading mx-auto text-center">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Real product views</p>
                    <h2 id="trainer-screens-heading">The roster, the plan and the signal to follow up.</h2>
                    <p class="mt-5">These screens demonstrate real Trainer App surfaces. Interface details may evolve as the app is updated.</p>
                </div>
                <div class="atlas-screen-stage mt-12 grid gap-6 md:grid-cols-3">
                    @foreach ([
                        ['image' => 'clients-720.webp', 'title' => 'Assigned clients', 'alt' => 'Atlas Trainer client roster showing assigned members.'],
                        ['image' => 'workout-builder-720.webp', 'title' => 'Workout builder', 'alt' => 'Atlas Trainer workout builder showing a structured member program.'],
                        ['image' => 'notifications-720.webp', 'title' => 'Alerts and updates', 'alt' => 'Atlas Trainer notifications screen showing coaching alerts.'],
                    ] as $screen)
                        <figure class="atlas-screen-card public-surface-premium overflow-hidden p-3">
                            <div class="public-media-frame border-0 shadow-none">
                                <img src="{{ asset('images/product/trainer/'.$screen['image']) }}" alt="{{ $screen['alt'] }}" class="public-app-shot" width="720" height="1280" loading="lazy" decoding="async">
                            </div>
                            <figcaption class="px-3 pb-2 pt-5 text-center text-sm font-semibold text-slate-800">{{ $screen['title'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="trainer-features" class="atlas-trainer-capabilities public-section bg-white" aria-labelledby="trainer-features-heading">
            <div class="public-container">
                <div class="public-section-heading">
                    <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-brand-600">Detailed capabilities</p>
                    <h2 id="trainer-features-heading">A practical workspace for current coaching workflows.</h2>
                    <p class="mt-5">Every capability below is part of the current trainer surface. Account verification, role and gym permissions apply where required.</p>
                </div>
                <div class="mt-12 space-y-5">
                    @foreach ($featureGroups as $feature)
                        <article class="atlas-trainer-capability public-surface-premium grid gap-6 p-6 sm:p-8 lg:grid-cols-[8rem_1fr_1fr] lg:p-10">
                            <span class="text-4xl font-bold tracking-[-0.04em] text-brand-500">{{ $feature['number'] }}</span>
                            <div>
                                <h3 class="text-2xl font-semibold tracking-[-0.025em] text-slate-950">{{ $feature['title'] }}</h3>
                                <p class="mt-4 leading-7 text-slate-600">{{ $feature['copy'] }}</p>
                            </div>
                            <ul class="space-y-3">
                                @foreach ($feature['items'] as $item)
                                    <li class="flex gap-3 text-sm leading-6 text-slate-700">
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

        <section class="public-section-compact bg-slate-50" aria-labelledby="trainer-boundary-heading">
            <div class="public-container public-surface-premium grid gap-8 p-7 sm:p-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-brand-600">Current product scope</p>
                    <h2 id="trainer-boundary-heading" class="mt-3 text-3xl font-semibold tracking-[-0.03em] text-slate-950">Two valid paths into the same coaching workspace.</h2>
                </div>
                <div class="space-y-4 leading-7 text-slate-600">
                    <p>Trainers can create an account and use their gym-assigned workflow immediately. After platform verification, the same account can also invite and coach personal members in a separate scope without changing gym, branch, role or assigned-member access.</p>
                    <p>Atlas does not promise public trainer discovery or hiring, paid online coaching, live video or voice sessions, or AI-generated programs. It is a coaching workspace—not a public trainer marketplace.</p>
                </div>
            </div>
        </section>

        <section class="public-section-compact bg-white">
            <div class="public-container public-surface-dark px-6 py-12 text-center sm:px-12 sm:py-16">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Trainer access</p>
                <h2 class="mx-auto mt-4 max-w-3xl text-3xl font-bold tracking-[-0.035em] text-white sm:text-5xl">Bring your coaching workflow into Atlas.</h2>
                <p class="mx-auto mt-5 max-w-2xl leading-7 text-slate-300">Tell us whether you are joining independently or through a gym. The Atlas team can guide the appropriate account and verification path.</p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('public.contact', ['inquiry_type' => 'trainer']) }}" class="public-button public-button-primary">Request trainer access</a>
                    @if ($whatsappHref)
                        <a href="{{ $whatsappHref }}" target="_blank" rel="noopener noreferrer" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Enquire on WhatsApp</a>
                    @endif
                    <a href="{{ url('/product') }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15">Explore the ecosystem</a>
                </div>
            </div>
        </section>
    </div>
</x-public.layouts.app>
