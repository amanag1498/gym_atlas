<x-public.layouts.app page-title="Home" page-description="One platform for gyms, trainers, and fitness members.">
    @php
        $statsGrid = [
            ['label' => 'Active gyms', 'value' => number_format((int) ($stats['active_gyms'] ?? 0)), 'note' => 'Live public profiles'],
            ['label' => 'Trainer layer', 'value' => number_format((int) ($stats['trainers'] ?? 0)), 'note' => 'Visible coaching network'],
            ['label' => 'Members tracked', 'value' => number_format((int) ($stats['members'] ?? 0)), 'note' => 'Connected operations'],
            ['label' => 'Trial demand', 'value' => number_format((int) ($stats['trial_requests'] ?? 0)), 'note' => 'Real inbound intent'],
        ];

        $pathways = [
            [
                'eyebrow' => 'For members',
                'title' => 'Choose a gym with more confidence.',
                'copy' => 'Search live listings, compare the right signals, and move into contact or trial without navigating directory clutter.',
                'href' => route('public.gyms.index'),
                'label' => 'Browse gyms',
                'accent' => '#2563eb',
                'surface' => 'linear-gradient(180deg, #ffffff, #f4f9ff)',
            ],
            [
                'eyebrow' => 'For gyms',
                'title' => 'Turn visibility into cleaner demand.',
                'copy' => 'Public discovery, leads, operations, and memberships stay connected so interest does not disappear between systems.',
                'href' => route('public.for-gyms'),
                'label' => 'See gym flow',
                'accent' => '#0f766e',
                'surface' => 'linear-gradient(180deg, #ffffff, #f3fbf8)',
            ],
            [
                'eyebrow' => 'For trainers',
                'title' => 'Work closer to real member progress.',
                'copy' => 'Coaching gains context when attendance, memberships, and member continuity are not fragmented into separate tools.',
                'href' => route('public.for-trainers'),
                'label' => 'See trainer flow',
                'accent' => '#7c3aed',
                'surface' => 'linear-gradient(180deg, #ffffff, #f8f5ff)',
            ],
        ];

        $featuredFallbackImages = [
            'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1517963879433-6ad2b056d712?auto=format&fit=crop&w=1200&q=80',
        ];
    @endphp

    <section class="hero-wrap js-fullheight" style="background-image: linear-gradient(110deg, rgba(10,17,32,0.58) 0%, rgba(20,58,138,0.24) 44%, rgba(255,255,255,0.04) 100%), url('https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1800&q=80'); min-height: 100vh; background-position: center;">
        <div class="overlay" style="background: transparent !important; opacity: 1 !important;"></div>
        <div class="container">
            <div class="row no-gutters align-items-center" style="min-height: 100vh; padding-top: 5.5rem; padding-bottom: 4rem;">
                <div class="col-xl-7 col-lg-8 ftco-animate public-reveal">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Atlas for modern fitness</div>
                    <h1 class="typewrite text-white" style="font-size: clamp(3.5rem, 8vw, 6.8rem); font-weight: 700; line-height: 0.94;" data-period="3200" data-type='@json(["Discover Better Gyms Faster.","Run Gym Operations With Clarity.","Coach Members With Real Context."])'>
                        <span class="wrap"></span>
                    </h1>
                    <p class="mt-4 public-reveal public-reveal-delay-1" style="max-width: 43rem; color: rgba(255,255,255,0.84); font-size: 1.06rem; line-height: 1.95;">
                        Atlas is designed around one uninterrupted story: someone discovers a gym, the gym receives better intent, and the relationship continues through memberships, coaching, and retention without breaking into separate systems.
                    </p>
                    <div class="mt-4 d-flex flex-wrap public-reveal public-reveal-delay-2" style="gap: 0.9rem;">
                        <a href="{{ route('public.gyms.index') }}" class="btn btn-primary p-3 px-4">Explore Gyms</a>
                        <a href="{{ route('public.for-gyms') }}" class="btn btn-white btn-outline-white p-3 px-4">Bring Your Gym</a>
                    </div>
                </div>
                <div class="col-xl-4 offset-xl-1 col-lg-4 d-none d-lg-block ftco-animate public-reveal public-reveal-delay-2">
                    <div class="atlas-hero-dashboard atlas-float p-4 p-xl-5">
                        <div class="d-flex align-items-center justify-content-between mb-4" style="position: relative; z-index: 1; gap: 1rem;">
                            <div>
                                <div class="atlas-dashboard-label">Live command view</div>
                                <div class="atlas-dashboard-value mt-2">Atlas OS</div>
                            </div>
                            <div style="width: 3rem; height: 3rem; border-radius: 1rem; background: linear-gradient(135deg, #67e8f9, #2563eb); box-shadow: 0 18px 44px rgba(37,99,235,0.34);"></div>
                        </div>
                        <div class="atlas-dashboard-row">
                            <div>
                                <div class="atlas-dashboard-label">Discovery</div>
                                <div class="atlas-dashboard-value">Verified leads</div>
                            </div>
                            <div class="atlas-mini-progress"><span style="width: 84%;"></span></div>
                        </div>
                        <div class="atlas-dashboard-row">
                            <div>
                                <div class="atlas-dashboard-label">Operations</div>
                                <div class="atlas-dashboard-value">Payments + attendance</div>
                            </div>
                            <div class="atlas-mini-progress"><span style="width: 72%;"></span></div>
                        </div>
                        <div class="atlas-dashboard-row">
                            <div>
                                <div class="atlas-dashboard-label">Coaching</div>
                                <div class="atlas-dashboard-value">Member progress</div>
                            </div>
                            <div class="atlas-mini-progress"><span style="width: 91%;"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light" style="padding-top: 2.5rem; padding-bottom: 3.5rem; background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">What Atlas fixes</h3>
                        <h2 class="mb-2">Most fitness journeys break the moment interest becomes action.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.92;">
                        Public discovery is usually generic, leads are weak, operations live somewhere else, and trainers lose context. Atlas matters because those pieces stay connected instead of being handed off badly.
                    </p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-5 ftco-animate public-reveal">
                    <div style="height: 100%; padding: 2.2rem; border-radius: 1.6rem; background: linear-gradient(135deg, #0f172a, #16315f); box-shadow: 0 28px 72px rgba(15,23,42,0.18);">
                        <div class="public-kicker mb-3" style="color: #93c5fd !important;">Before Atlas</div>
                        <div style="display: grid; gap: 1rem;">
                            @foreach ([
                                'Discovery feels noisy and generic.',
                                'Lead quality falls between marketing and ops.',
                                'Trainers work without live member context.',
                            ] as $line)
                                <div class="d-flex align-items-start" style="gap: 0.8rem;">
                                    <div style="width: 0.55rem; height: 0.55rem; border-radius: 9999px; background: linear-gradient(135deg, #93c5fd, #2563eb); margin-top: 0.45rem;"></div>
                                    <p class="mb-0" style="color: rgba(255,255,255,0.76); line-height: 1.78;">{{ $line }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row">
                        @foreach ($statsGrid as $item)
                            <div class="col-md-6 ftco-animate public-reveal {{ $loop->index % 2 === 1 ? 'public-reveal-delay-1' : '' }}">
                                <div class="mb-4 atlas-metric-card" style="height: calc(100% - 1.5rem); padding: 2rem 1.7rem; border-radius: 1.45rem; background: rgba(255,255,255,0.92); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 18px 44px rgba(15,23,42,0.06);">
                                    <div style="font-size: 0.78rem; letter-spacing: 0.18em; text-transform: uppercase; color: #60a5fa; font-weight: 700;">{{ $item['label'] }}</div>
                                    <div class="mt-3" style="font-size: clamp(2.2rem, 4vw, 3.2rem); font-weight: 700; line-height: 1; color: #0f172a;">{{ $item['value'] }}</div>
                                    <div class="mt-3" style="color: #64748b; font-size: 0.95rem; line-height: 1.8;">{{ $item['note'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section" style="background: #ffffff;">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-6 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Who it serves</h3>
                        <h2 class="mb-2">The same platform speaks differently to each side of the relationship.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.92;">
                        Members need clarity, gyms need cleaner demand, and trainers need continuity. Atlas is more valuable because it handles all three without forcing them into separate disconnected tools.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ($pathways as $item)
                    <div class="col-lg-4 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <a href="{{ $item['href'] }}" class="d-block text-decoration-none mb-4">
                            <div style="height: 100%; min-height: 22rem; padding: 2rem 1.8rem; border-radius: 1.55rem; background: {{ $item['surface'] }}; border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 20px 48px rgba(15,23,42,0.07);">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div style="font-size: 0.76rem; letter-spacing: 0.16em; text-transform: uppercase; color: {{ $item['accent'] }}; font-weight: 700;">{{ $item['eyebrow'] }}</div>
                                    <div style="width: 2.6rem; height: 2.6rem; border-radius: 9999px; background: rgba(255,255,255,0.9); border: 1px solid rgba(148,163,184,0.14); display: inline-flex; align-items: center; justify-content: center; color: {{ $item['accent'] }}; font-size: 1rem; font-weight: 700;">→</div>
                                </div>
                                <h3 style="font-size: 1.48rem; font-weight: 700; color: #0f172a; line-height: 1.08;">{{ $item['title'] }}</h3>
                                <p class="mt-3 mb-0" style="color: #475569; line-height: 1.9;">{{ $item['copy'] }}</p>
                                <div class="mt-4" style="color: {{ $item['accent'] }}; font-size: 0.95rem; font-weight: 600;">{{ $item['label'] }} →</div>
                            </div>
                        </a>
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
                        <h3 class="subheading">How the story moves</h3>
                        <h2 class="mb-2">A better search becomes a better relationship.</h2>
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <p class="mb-0" style="color: #475569; line-height: 1.9;">
                        A homepage should make the product easy to imagine. The simplest way to understand Atlas is to follow one journey from discovery into operations without the handoff ever breaking.
                    </p>
                </div>
            </div>

            <div class="row">
                @foreach ([
                    ['step' => '01', 'title' => 'A member discovers a gym', 'copy' => 'They begin with live, trustworthy listings instead of static directories and weak profile pages.'],
                    ['step' => '02', 'title' => 'A gym receives stronger intent', 'copy' => 'That interest turns into structured contact or trial activity that enters the actual gym workflow.'],
                    ['step' => '03', 'title' => 'The relationship continues inside Atlas', 'copy' => 'Memberships, coaching context, attendance, and retention stay in the same story rather than moving to unrelated tools.'],
                ] as $step)
                    <div class="col-lg-4 ftco-animate public-reveal {{ $loop->index === 1 ? 'public-reveal-delay-1' : ($loop->index === 2 ? 'public-reveal-delay-2' : '') }}">
                        <div style="height: 100%; padding: 2rem 1.75rem; border-radius: 1.45rem; background: rgba(255,255,255,0.88); border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 18px 40px rgba(15,23,42,0.06);">
                            <div style="font-size: 0.78rem; letter-spacing: 0.18em; text-transform: uppercase; color: #60a5fa; font-weight: 700;">{{ $step['step'] }}</div>
                            <h3 class="mt-4" style="font-size: 1.34rem; font-weight: 700; color: #0f172a; line-height: 1.12;">{{ $step['title'] }}</h3>
                            <p class="mt-3 mb-0" style="color: #475569; line-height: 1.9;">{{ $step['copy'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ftco-section" style="background: #ffffff;">
        <div class="container">
            <div class="row align-items-end mb-5">
                <div class="col-lg-7 ftco-animate public-reveal">
                    <div class="heading-section mb-0">
                        <h3 class="subheading">Featured gyms</h3>
                        <h2 class="mb-2">Start with a tighter shortlist of profiles worth opening first.</h2>
                    </div>
                </div>
                <div class="col-lg-4 offset-lg-1 ftco-animate public-reveal public-reveal-delay-1">
                    <div class="text-lg-right">
                        <a href="{{ route('public.gyms.index') }}" style="font-size: 0.84rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #2563eb;">See all gyms →</a>
                    </div>
                </div>
            </div>

            @if ($featuredGyms->isNotEmpty())
                <div class="row">
                    @foreach ($featuredGyms->take(6) as $gym)
                        @php($price = $gym->fee_summary['min_price'] ?? null)
                        @php($heroImage = $gym->cover_image_url ?: $gym->cover_image ?: $gym->logo_url ?: $gym->logo ?: $featuredFallbackImages[$loop->index % 6])
                        <div class="col-md-6 col-xl-4 ftco-animate public-reveal {{ $loop->index % 3 === 1 ? 'public-reveal-delay-1' : ($loop->index % 3 === 2 ? 'public-reveal-delay-2' : '') }}">
                            <a href="{{ route('public.gyms.show', $gym->slug) }}" class="d-block text-decoration-none mb-4">
                                <div style="overflow: hidden; border-radius: 1.5rem; background: #ffffff; border: 1px solid rgba(148,163,184,0.14); box-shadow: 0 24px 54px rgba(15,23,42,0.08);">
                                    <div style="height: 17rem; background-image: url('{{ $heroImage }}'); background-size: cover; background-position: center;"></div>
                                    <div class="p-4 p-md-5">
                                        <div class="d-flex justify-content-between align-items-start mb-3" style="gap: 1rem;">
                                            <div>
                                                <h3 class="mb-2" style="font-size: 1.42rem; font-weight: 700; color: #0f172a; line-height: 1.08;">{{ $gym->name }}</h3>
                                                <p class="mb-0" style="color: #64748b; line-height: 1.7;">{{ collect([$gym->city, $gym->state])->filter()->implode(', ') ?: 'Location available on profile' }}</p>
                                            </div>
                                            <div style="padding: 0.45rem 0.7rem; border-radius: 9999px; background: {{ $gym->is_verified ? 'rgba(37,99,235,0.08)' : 'rgba(148,163,184,0.12)' }}; color: {{ $gym->is_verified ? '#2563eb' : '#475569' }}; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;">
                                                {{ $gym->is_verified ? 'Verified' : 'Live' }}
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-end" style="gap: 1rem;">
                                            <div>
                                                <div style="font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Starting</div>
                                                <div class="mt-2" style="color: #0f172a; font-size: 1.12rem; font-weight: 700;">
                                                    @if ($gym->show_pricing && $price !== null)
                                                        ₹{{ number_format((float) $price, 0) }}
                                                    @else
                                                        On enquiry
                                                    @endif
                                                </div>
                                            </div>
                                            <div style="color: #2563eb; font-size: 0.92rem; font-weight: 600;">Open profile →</div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="row justify-content-center">
                    <div class="col-md-8 text-center">
                        <p style="color: #94a3b8; line-height: 1.85;">Featured gyms will appear here as active public profiles are promoted.</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="ftco-section bg-light" style="padding-top: 1rem; background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);">
        <div class="container">
            <div class="rounded-[1.8rem] p-5 p-md-6 ftco-animate public-reveal" style="background: linear-gradient(135deg, #ffffff, #f1f7ff); border: 1px solid rgba(148,163,184,0.16); box-shadow: 0 28px 60px rgba(15,23,42,0.08); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -5rem; right: -4rem; width: 14rem; height: 14rem; border-radius: 9999px; background: rgba(37,99,235,0.08); filter: blur(20px);"></div>
                <div style="position: absolute; bottom: -4rem; left: -3rem; width: 11rem; height: 11rem; border-radius: 9999px; background: rgba(125,211,252,0.14); filter: blur(18px);"></div>
                <div class="row align-items-center" style="position: relative; z-index: 1;">
                    <div class="col-lg-7">
                        <div class="public-kicker mb-3">Start here</div>
                        <h2 class="mb-3" style="font-size: clamp(2.2rem, 4vw, 3.4rem); font-weight: 700; line-height: 1.02; color: #0f172a;">Atlas becomes valuable when discovery turns into a continuing relationship.</h2>
                        <p class="mb-0" style="max-width: 38rem; color: #475569; line-height: 1.9;">
                            Start by exploring the network, or bring your gym into a system where visibility, leads, operations, and coaching stay connected instead of breaking apart.
                        </p>
                    </div>
                    <div class="col-lg-4 offset-lg-1 mt-4 mt-lg-0 public-reveal public-reveal-delay-1">
                        <div class="d-flex flex-column" style="gap: 0.85rem;">
                            <a href="{{ route('public.gyms.index') }}" class="btn btn-primary p-3 px-4 text-center">Find Gyms</a>
                            <a href="{{ route('public.for-gyms') }}" class="btn btn-white btn-outline-white p-3 px-4 text-center">Bring Your Gym</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
