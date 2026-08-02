<x-public.layouts.app page-title="Atlas Gym Ecosystem" page-description="Train independently with personal workouts, diet plans and progress, then optionally connect with gyms, trainers and gym operations through one fitness ecosystem.">
    @php
        $roles = [
            [
                'number' => '01',
                'title' => 'Member App',
                'copy' => 'Train independently with personal workouts, diet plans and progress, then connect a gym whenever you choose.',
                'features' => ['Personal workouts, diet and progress', 'Optional gym discovery and connection', 'Membership, attendance and trainer context after connecting'],
                'href' => route('public.member-app'),
                'cta' => 'Explore the Member App',
            ],
            [
                'number' => '02',
                'title' => 'Trainer App',
                'copy' => 'Coach gym-assigned members and, after platform verification, invite your own personal clients without mixing the two relationships.',
                'features' => ['Gym-assigned and personal-member roster', 'Workout and diet plans scoped to each relationship', 'Verification for personal coaching access'],
                'href' => route('public.for-trainers'),
                'cta' => 'Explore trainer tools',
            ],
            [
                'number' => '03',
                'title' => 'Gym Admin',
                'copy' => 'Run day-to-day gym operations while keeping the public listing and member experience connected.',
                'features' => ['Members, staff, trainers and branches', 'Memberships, attendance, payments and dues', 'Leads, reports, announcements and audit logs'],
                'href' => route('public.for-gyms'),
                'cta' => 'Explore gym management',
            ],
        ];

        $journey = [
            ['number' => '1', 'title' => 'Start personally', 'copy' => 'A member creates workouts and diet plans and begins recording progress without needing a gym.'],
            ['number' => '2', 'title' => 'Connect optionally', 'copy' => 'When ready, the member discovers a gym, submits a trial request, or responds to an invitation.'],
            ['number' => '3', 'title' => 'Add gym context', 'copy' => 'Membership, attendance, trainer assignment and gym-scoped features join the personal fitness history.'],
            ['number' => '4', 'title' => 'Understand', 'copy' => 'Members see their journey while authorized gym roles review operations and history.'],
        ];
    @endphp

    <style>
        .home-hero { overflow: hidden; padding: clamp(6.5rem, 12vw, 10rem) 0 clamp(4rem, 8vw, 7rem); background: radial-gradient(circle at 80% 18%, rgba(70,95,255,.24), transparent 27rem), radial-gradient(circle at 16% 85%, rgba(34,211,238,.12), transparent 24rem), linear-gradient(145deg, #020617, #0b1735 58%, #12245b); color: #fff; }
        .home-hero-grid, .home-split, .home-admin-grid { display: grid; gap: clamp(2rem, 6vw, 5rem); align-items: center; }
        .home-eyebrow { display: inline-flex; align-items: center; gap: .55rem; color: var(--atlas-public-brand-500); font-size: .78rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .home-eyebrow::before { width: 1.5rem; height: 2px; background: currentColor; content: ''; }
        .home-hero .home-eyebrow { color: #9cb9ff; }
        .home-title { max-width: 47rem; margin: 1.25rem 0 1.5rem; color: #fff; font-size: clamp(2.85rem, 7vw, 5.75rem); font-weight: 700; letter-spacing: -.055em; line-height: .98; }
        .home-lead { max-width: 41rem; margin: 0; color: rgba(226,232,240,.86); font-size: clamp(1.05rem, 2vw, 1.22rem); line-height: 1.78; }
        .home-actions { display: flex; flex-wrap: wrap; gap: .8rem; margin-top: 2rem; }
        .home-hero .public-button-secondary { border-color: rgba(255,255,255,.18); background: rgba(255,255,255,.08); color: #fff; }
        .home-devices { position: relative; min-height: clamp(29rem, 65vw, 39rem); }
        .home-phone { position: absolute; width: min(68%, 18rem); overflow: hidden; border: .45rem solid #101827; border-radius: 2.4rem; background: #101827; box-shadow: 0 34px 80px rgba(0,0,0,.38); }
        .home-phone img { display: block; width: 100%; height: auto; border-radius: 1.9rem; }
        .home-phone:first-child { left: 0; top: 3.5rem; transform: rotate(-5deg); }
        .home-phone:last-child { right: 0; top: 0; transform: rotate(5deg); }
        .home-phone-label { position: absolute; z-index: 2; padding: .55rem .8rem; border: 1px solid rgba(255,255,255,.16); border-radius: 999px; background: rgba(15,23,42,.88); box-shadow: 0 12px 32px rgba(0,0,0,.24); color: #fff; font-size: .75rem; font-weight: 700; backdrop-filter: blur(12px); }
        .home-phone:first-child .home-phone-label { right: -.9rem; top: 1rem; }
        .home-phone:last-child .home-phone-label { bottom: 1.2rem; left: -1rem; }
        .home-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1px; overflow: hidden; border: 1px solid var(--atlas-public-slate-200); border-radius: var(--atlas-public-radius-xl); background: var(--atlas-public-slate-200); box-shadow: var(--atlas-public-shadow-sm); }
        .home-stat { min-width: 0; padding: clamp(1.25rem, 3vw, 2rem); background: #fff; }
        .home-stat strong { display: block; color: var(--atlas-public-slate-950); font-size: clamp(1.9rem, 4vw, 3rem); line-height: 1; }
        .home-stat span { display: block; margin-top: .65rem; color: var(--atlas-public-slate-700); font-weight: 700; }
        .home-stat small { display: block; margin-top: .35rem; color: var(--atlas-public-slate-500); line-height: 1.55; }
        .home-role-grid { display: grid; gap: 1rem; margin-top: 2.5rem; }
        .home-role { display: flex; height: 100%; flex-direction: column; padding: clamp(1.4rem, 3vw, 2rem); }
        .home-role-number { color: var(--atlas-public-brand-500); font-size: .76rem; font-weight: 700; letter-spacing: .14em; }
        .home-role h3 { margin: 1rem 0 .65rem; color: var(--atlas-public-slate-950); font-size: 1.45rem; font-weight: 700; }
        .home-role p { color: var(--atlas-public-slate-600); line-height: 1.72; }
        .home-list { display: grid; gap: .65rem; margin: .25rem 0 1.5rem; padding: 0; list-style: none; }
        .home-list li { display: flex; gap: .65rem; color: var(--atlas-public-slate-700); font-size: .93rem; line-height: 1.55; }
        .home-list li::before { flex: 0 0 .45rem; width: .45rem; height: .45rem; margin-top: .5rem; border-radius: 50%; background: var(--atlas-public-brand-500); content: ''; }
        .home-role a { margin-top: auto; color: var(--atlas-public-brand-600); font-size: .9rem; font-weight: 700; }
        .home-soft { background: linear-gradient(180deg, #f8fafc, #eff3ff); }
        .home-copy h2 { margin: 1rem 0 1.25rem; color: var(--atlas-public-slate-950); font-size: clamp(2.1rem, 5vw, 3.65rem); font-weight: 700; letter-spacing: -.045em; line-height: 1.04; }
        .home-copy > p { color: var(--atlas-public-slate-600); font-size: 1.05rem; line-height: 1.8; }
        .home-shot { padding: clamp(.7rem, 2vw, 1.15rem); background: linear-gradient(145deg, #dfe8ff, #fff 52%, #e0f7fb); }
        .home-shot img { max-height: 42rem; object-fit: contain; }
        .home-detail-grid { display: grid; gap: .8rem; margin-top: 1.75rem; }
        .home-detail { padding: 1rem 1.05rem; border: 1px solid var(--atlas-public-slate-200); border-radius: 1rem; background: rgba(255,255,255,.9); }
        .home-detail strong { display: block; color: var(--atlas-public-slate-900); }
        .home-detail span { display: block; margin-top: .25rem; color: var(--atlas-public-slate-500); font-size: .88rem; line-height: 1.5; }
        .home-admin-grid { align-items: stretch; }
        .home-admin { padding: clamp(1.5rem, 4vw, 2.5rem); }
        .home-admin h3 { margin: 1rem 0; color: inherit; font-size: clamp(1.65rem, 3vw, 2.2rem); font-weight: 700; }
        .home-admin p { color: inherit; opacity: .78; line-height: 1.75; }
        .home-admin .home-list li { color: inherit; opacity: .9; }
        .home-admin .home-list li::before { background: #67e8f9; }
        .home-admin-light { color: var(--atlas-public-slate-900); }
        .home-admin-light p, .home-admin-light .home-list li { color: var(--atlas-public-slate-600); opacity: 1; }
        .home-journey { display: grid; gap: 1rem; margin-top: 2.5rem; counter-reset: journey; }
        .home-journey-step { position: relative; padding: 1.5rem; border-left: 2px solid var(--atlas-public-brand-300); background: #fff; }
        .home-journey-step b { display: inline-flex; width: 2rem; height: 2rem; align-items: center; justify-content: center; border-radius: 50%; background: var(--atlas-public-brand-50); color: var(--atlas-public-brand-600); }
        .home-journey-step h3 { margin: 1rem 0 .55rem; color: var(--atlas-public-slate-950); font-size: 1.25rem; font-weight: 700; }
        .home-journey-step p { margin: 0; color: var(--atlas-public-slate-600); line-height: 1.7; }
        .home-gym-grid { display: grid; gap: 1.25rem; margin-top: 2.5rem; }
        .home-gym { display: flex; height: 100%; overflow: hidden; flex-direction: column; color: inherit; }
        .home-gym:hover { color: inherit; text-decoration: none; transform: translateY(-2px); }
        .home-gym-media { position: relative; aspect-ratio: 16 / 10; overflow: hidden; background: linear-gradient(145deg, #dfe8ff, #e2e8f0); }
        .home-gym-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; }
        .home-gym:hover .home-gym-media img { transform: scale(1.025); }
        .home-gym-badge { position: absolute; left: 1rem; top: 1rem; padding: .4rem .65rem; border-radius: 999px; background: rgba(255,255,255,.92); color: var(--atlas-public-brand-700); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .home-gym-body { display: flex; flex: 1; flex-direction: column; padding: 1.35rem; }
        .home-gym h3 { margin: 0; color: var(--atlas-public-slate-950); font-size: 1.3rem; font-weight: 700; }
        .home-gym p { margin: .45rem 0 1rem; color: var(--atlas-public-slate-500); }
        .home-gym-meta { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-top: auto; color: var(--atlas-public-slate-600); font-size: .85rem; }
        .home-gym-meta strong { display: block; margin-top: .25rem; color: var(--atlas-public-slate-950); font-size: 1.05rem; }
        .home-cta { position: relative; overflow: hidden; padding: clamp(2rem, 6vw, 4rem); }
        .home-cta-copy { position: relative; z-index: 2; max-width: 42rem; }
        .home-cta h2 { margin: 1rem 0; color: #fff; font-size: clamp(2rem, 5vw, 3.5rem); font-weight: 700; letter-spacing: -.04em; line-height: 1.05; }
        .home-cta p { color: rgba(226,232,240,.82); font-size: 1.05rem; line-height: 1.75; }
        @media (min-width: 640px) { .home-detail-grid { grid-template-columns: repeat(2, 1fr); } .home-gym-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 768px) { .home-role-grid, .home-journey { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 992px) { .home-hero-grid, .home-split { grid-template-columns: minmax(0, 1.05fr) minmax(22rem, .8fr); } .home-role-grid { grid-template-columns: repeat(3, 1fr); } .home-journey { grid-template-columns: repeat(4, 1fr); } .home-gym-grid { grid-template-columns: repeat(3, 1fr); } .home-split-reverse .home-copy { order: 2; } .home-split-reverse .home-shot { order: 1; } }
        @media (max-width: 420px) { .home-title { font-size: 2.55rem; } .home-devices { min-height: 27rem; } .home-phone { width: 70%; border-width: .35rem; border-radius: 1.9rem; } .home-phone img { border-radius: 1.5rem; } }
        /* Editorial composition: the homepage is a guided story, not a card catalogue. */
        .home-role-grid { align-items: stretch; }
        .home-role { position: relative; overflow: hidden; border: 0; border-radius: 1.65rem; box-shadow: 0 22px 56px rgba(11,29,67,.09); }
        .home-role::after { position: absolute; right: -3rem; bottom: -3rem; width: 8rem; height: 8rem; border: 1px solid rgba(70,95,255,.12); border-radius: 50%; content: ''; }
        .home-role:nth-child(2) { background: #071229; color: #fff; }
        .home-role:nth-child(2) h3 { color: #fff; }
        .home-role:nth-child(2) p, .home-role:nth-child(2) li { color: #bdc8dd; }
        .home-shot { position: relative; border: 0; border-radius: 2rem; box-shadow: 0 36px 80px rgba(12,31,76,.15); transform: rotate(1.5deg); }
        .home-split-reverse .home-shot { transform: rotate(-1.5deg); }
        .home-shot::before { position: absolute; inset: 8% -6% -5% 18%; z-index: -1; border-radius: 2rem; background: #dfe6ff; content: ''; }
        .home-split-reverse .home-shot::before { inset: 8% 18% -5% -6%; background: #dff8fa; }
        .home-journey { position: relative; }
        .home-journey::before { position: absolute; top: 1.05rem; right: 8%; left: 8%; height: 2px; background: linear-gradient(90deg,#6178ff,#31cedc); content: ''; }
        .home-journey-step { z-index: 1; border: 0; border-radius: 1.5rem; box-shadow: 0 18px 46px rgba(11,29,67,.08); }
        .home-journey-step b { box-shadow: 0 0 0 7px #f6f8fc; }
        @media (min-width: 992px) { .home-role-grid { grid-template-columns: repeat(3,1fr); } .home-role:nth-child(1), .home-role:nth-child(3) { transform: translateY(1.5rem); } }
        @media (max-width: 767px) { .home-role-grid { display: flex; margin-inline: calc(var(--atlas-public-gutter,1rem) * -1); padding: 0 var(--atlas-public-gutter,1rem) 1.5rem; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; } .home-role-grid::-webkit-scrollbar { display: none; } .home-role { flex: 0 0 min(84vw,21rem); scroll-snap-align: start; } .home-journey::before { top: 0; bottom: 0; left: 1rem; width: 2px; height: auto; } .home-journey-step { margin-left: 1.5rem; } .home-shot, .home-split-reverse .home-shot { transform: none; } }
    </style>

    <section class="home-hero">
        <div class="public-container-wide home-hero-grid">
            <div>
                <span class="home-eyebrow">One connected fitness ecosystem</span>
                <h1 class="home-title">Start your fitness journey. Connect the ecosystem when you need it.</h1>
                <p class="home-lead">Use the Member App independently for workouts, diet and progress. Connect with a gym later for membership, attendance, trainer assignments and gym-scoped experiences.</p>
                <div class="home-actions">
                    <a class="public-button public-button-primary" href="{{ route('public.member-app') }}">Explore the Member App <span aria-hidden="true">→</span></a>
                    <a class="public-button public-button-secondary" href="{{ route('public.gyms.index') }}">Find a gym when ready</a>
                </div>
            </div>
            <div class="home-devices" aria-label="Atlas Member and Trainer application screens">
                <figure class="home-phone">
                    <figcaption class="home-phone-label">Member App</figcaption>
                    <img src="{{ asset('images/product/member/dashboard-720.webp') }}" width="720" height="1280" alt="Atlas Member dashboard showing membership and fitness activity information." fetchpriority="high">
                </figure>
                <figure class="home-phone">
                    <figcaption class="home-phone-label">Trainer App</figcaption>
                    <img src="{{ asset('images/product/trainer/dashboard-720.webp') }}" width="720" height="1280" alt="Atlas Trainer dashboard showing assigned-member coaching work." decoding="async">
                </figure>
            </div>
        </div>
    </section>

    <section class="public-section home-soft" aria-labelledby="ecosystem-title">
        <div class="public-container-wide">
            <div class="public-section-heading">
                <span class="home-eyebrow">Three connected workspaces</span>
                <h2 id="ecosystem-title">Everyone sees the part of Atlas built for their role.</h2>
                <p>The tools are separated by access and responsibility, while the underlying fitness relationship stays connected.</p>
            </div>
            <div class="home-role-grid">
                @foreach ($roles as $role)
                    <article class="home-role public-surface-premium">
                        <span class="home-role-number">{{ $role['number'] }}</span>
                        <h3>{{ $role['title'] }}</h3>
                        <p>{{ $role['copy'] }}</p>
                        <ul class="home-list">
                            @foreach ($role['features'] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $role['href'] }}">{{ $role['cta'] }} <span aria-hidden="true">→</span></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="public-section" aria-labelledby="member-title">
        <div class="public-container home-split">
            <div class="home-copy">
                <span class="home-eyebrow">Member App</span>
                <h2 id="member-title">Your training and progress come first. A gym connection is optional.</h2>
                <p>Members can create personal workouts and diet plans and record progress without joining a gym. Connecting later adds gym invitations, membership, attendance, trainer assignments and other gym-scoped features.</p>
                <div class="home-detail-grid">
                    <div class="home-detail"><strong>Train with structure</strong><span>Follow assigned workouts or build personal plans, then log sets, reps, weight and completed sessions.</span></div>
                    <div class="home-detail"><strong>Review progress</strong><span>See workout history, volume, exercise trends, personal records, measurements and progress photos.</span></div>
                    <div class="home-detail"><strong>Plan meals</strong><span>Follow assigned meal-based diet plans, use templates, create personal plans and mark meals complete.</span></div>
                    <div class="home-detail"><strong>Connect when ready</strong><span>Add membership, attendance and assigned-trainer context later while keeping your personal history together.</span></div>
                </div>
                <div class="home-actions"><a class="public-button public-button-primary" href="{{ route('public.member-app') }}">Explore individual Member features</a><a class="public-button public-button-secondary" href="{{ route('public.gyms.index') }}">Find a gym later</a></div>
            </div>
            <figure class="home-shot public-media-frame">
                <img class="public-app-shot" src="{{ asset('images/product/member/workouts-720.webp') }}" width="720" height="1280" alt="Atlas Member workouts screen showing assigned and personal workout plans." loading="lazy" decoding="async">
            </figure>
        </div>
    </section>

    <section class="public-section home-soft" aria-labelledby="trainer-title">
        <div class="public-container home-split home-split-reverse">
            <div class="home-copy">
                <span class="home-eyebrow">Trainer App</span>
                <h2 id="trainer-title">Coach with the member context that matters next.</h2>
                <p>Gym-connected trainers work with members assigned by their gym. Any trainer can separately complete platform verification to invite personal clients and manage their plans; verification never changes the gym relationship.</p>
                <div class="home-detail-grid">
                    <div class="home-detail"><strong>Know the roster</strong><span>Review assigned members, today’s queue, attendance, progress, workout history and follow-up context.</span></div>
                    <div class="home-detail"><strong>Build programs</strong><span>Verified trainers can create reusable workout templates and structured member plans, then assign them.</span></div>
                    <div class="home-detail"><strong>Support nutrition</strong><span>Create and reuse meal-based diet plans when the gym grants the required permission.</span></div>
                    <div class="home-detail"><strong>Keep work together</strong><span>Manage notes and tasks, trial follow-ups, alerts, announcements and member conversations.</span></div>
                </div>
                <div class="home-actions"><a class="public-button public-button-primary" href="{{ route('public.for-trainers') }}">See Atlas for trainers</a></div>
            </div>
            <figure class="home-shot public-media-frame">
                <img class="public-app-shot" src="{{ asset('images/product/trainer/workout-builder-720.webp') }}" width="720" height="1280" alt="Atlas Trainer workout builder used to create and assign structured member plans." loading="lazy" decoding="async">
            </figure>
        </div>
    </section>

    <section class="public-section" aria-labelledby="operations-title">
        <div class="public-container-wide">
            <div class="public-section-heading">
                <span class="home-eyebrow">Gym operations</span>
                <h2 id="operations-title">The apps are backed by a workspace for running the gym.</h2>
                <p>Atlas gives gym teams role-based access to the records and controls behind daily member operations.</p>
            </div>
            <div class="home-admin-grid">
                <article class="home-admin public-surface-dark">
                    <span class="home-eyebrow">Gym Admin</span>
                    <h3>Manage the gym beyond the front desk.</h3>
                    <p>Gym teams can organize branches, people, memberships and day-to-day records while maintaining the information members see.</p>
                    <ul class="home-list">
                        <li>Member, staff, trainer and branch management</li>
                        <li>Plans, memberships, custom fees and audit history</li>
                        <li>Payments, dues, invoices and reversible ledger records</li>
                        <li>Attendance, correction review and supported biometric workflows</li>
                        <li>Trials, announcements, reports, exports and public listing controls</li>
                    </ul>
                    <a class="public-button public-button-primary" href="{{ route('public.for-gyms') }}">Explore gym management</a>
                </article>
            </div>
        </div>
    </section>

    <section class="public-section home-soft" aria-labelledby="journey-title">
        <div class="public-container-wide">
            <div class="public-section-heading">
                <span class="home-eyebrow">How Atlas works</span>
                <h2 id="journey-title">Follow one journey across the ecosystem.</h2>
                <p>Start with a personal fitness record, then connect discovery, membership, coaching and gym operations only when those relationships become relevant.</p>
            </div>
            <div class="home-journey">
                @foreach ($journey as $step)
                    <article class="home-journey-step public-surface-premium">
                        <b>{{ $step['number'] }}</b>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="public-section" aria-labelledby="featured-title">
        <div class="public-container-wide">
            <div class="public-section-heading">
                <span class="home-eyebrow">Featured gyms</span>
                <h2 id="featured-title">Open a live profile and explore the details.</h2>
                <p>Featured public profiles can include location, facilities, branches, trainers and plan information supplied through Atlas.</p>
            </div>

            @if ($featuredGyms->isNotEmpty())
                <div class="home-gym-grid">
                    @foreach ($featuredGyms->take(6) as $gym)
                        @php($price = $gym->fee_summary['min_price'] ?? null)
                        @php($heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo)
                        <a class="home-gym public-surface-premium" href="{{ route('public.gyms.show', $gym->slug) }}">
                            <div class="home-gym-media">
                                @if ($heroImage)
                                    <img src="{{ $heroImage }}" width="1200" height="800" alt="{{ $gym->name }} gym profile cover." loading="lazy" decoding="async">
                                @endif
                                <span class="home-gym-badge">{{ $gym->is_verified ? 'Atlas verified' : 'Public profile' }}</span>
                            </div>
                            <div class="home-gym-body">
                                <h3>{{ $gym->name }}</h3>
                                <p>{{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Location available on profile' }}</p>
                                <div class="home-gym-meta">
                                    <span>Starting<strong>@if ($gym->show_pricing && $price !== null) ₹{{ number_format((float) $price, 0) }} @else On enquiry @endif</strong></span>
                                    <span>Open profile <span aria-hidden="true">→</span></span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="public-surface-premium" style="margin-top: 2rem; padding: 2rem; text-align: center; color: var(--atlas-public-slate-500);">Featured gym profiles will appear here when they are available.</div>
            @endif

            <div class="home-actions" style="justify-content: center;"><a class="public-button public-button-secondary" href="{{ route('public.gyms.index') }}">Browse all gyms</a></div>
        </div>
    </section>

    <section class="public-section-compact">
        <div class="public-container-wide">
            <div class="home-cta public-surface-dark">
                <div class="home-cta-copy">
                    <span class="home-eyebrow">Choose your next step</span>
                    <h2>See the platform from the side that matters to you.</h2>
                    <p>Explore public gym profiles as a member, learn how Atlas supports gym operations, or contact the team about the appropriate access path.</p>
                    <div class="home-actions">
                        <a class="public-button public-button-primary" href="{{ route('public.gyms.index') }}">Find a gym</a>
                        <a class="public-button public-button-secondary" href="{{ route('public.contact') }}">Contact Atlas</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
