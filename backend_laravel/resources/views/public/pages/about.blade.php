@php
    $principles = [
        ['title' => 'Connect the whole journey', 'copy' => 'Discovery, trial follow-up, membership, gym operations and coaching should continue as one understandable relationship.'],
        ['title' => 'Make work visible', 'copy' => 'Members, trainers and operators need the right context at the moment they must decide or act.'],
        ['title' => 'Keep control intentional', 'copy' => 'Public visibility, role access, gym permissions, audit history and user safety controls should remain explicit.'],
        ['title' => 'Turn records into follow-up', 'copy' => 'Attendance, dues, plans, notes, progress and trial status matter when they guide the next useful action.'],
    ];
@endphp

<x-public.layouts.app page-title="About Atlas" page-description="Atlas connects gym discovery, member continuity, trainer coaching and gym management in one fitness ecosystem.">
    <section class="atlas-editorial-hero pb-20 pt-32 sm:pb-24 sm:pt-40">
        <div class="public-container-wide grid items-center gap-8 lg:grid-cols-[1.02fr_.98fr]">
            <div class="relative z-10 py-6 lg:py-14">
                <p class="public-eyebrow">The Atlas story</p>
                <h1 class="mt-6 max-w-4xl text-4xl font-semibold leading-[.98] tracking-[-.055em] text-white sm:text-6xl lg:text-7xl">A connected operating layer for the gym ecosystem.</h1>
                <p class="mt-7 max-w-2xl text-base leading-8 text-slate-300 sm:text-lg">Atlas exists so finding a gym, joining it, running it and coaching within it do not become separate stories held together by spreadsheets, screenshots and chat threads.</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ url('/how-it-works') }}" class="public-button public-button-primary">Follow the journey</a>
                    <a href="{{ url('/product') }}" class="public-button public-button-ghost-light">Explore the platform</a>
                </div>
            </div>
            <div class="atlas-orbit-map" aria-label="The four connected Atlas product surfaces">
                <div class="atlas-orbit-core"><img src="{{ asset('images/public-site/brand/atlas-mark-512.png') }}" width="512" height="512" alt="Atlas" class="h-16 w-16 object-contain"></div>
                @foreach ([['Member App','Discover and progress'],['Trainer App','Plan and follow up'],['Gym Admin','Operate and retain']] as $surface)
                    <div class="atlas-orbit-node"><strong>{{ $surface[0] }}</strong><span>{{ $surface[1] }}</span></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="public-section bg-white" aria-labelledby="about-mission-heading">
        <div class="public-container-wide grid gap-14 lg:grid-cols-[.82fr_1.18fr]">
            <div class="lg:sticky lg:top-28 lg:self-start">
                <p class="public-kicker">Why Atlas exists</p>
                <h2 id="about-mission-heading" class="mt-5 max-w-xl text-4xl font-semibold leading-[1.03] tracking-[-.05em] text-slate-950 sm:text-5xl">The fitness journey should feel continuous.</h2>
                <p class="mt-6 max-w-xl leading-8 text-slate-600">A visitor should understand a gym before enquiring. A gym should receive structured demand and continue it into membership. A trainer should coach with member context. Members should see their progress without losing continuity.</p>
            </div>
            <div>
                @foreach ($principles as $principle)
                    <article class="atlas-story-panel grid gap-4 sm:grid-cols-[4rem_1fr]">
                        <span class="atlas-story-index">0{{ $loop->iteration }}</span>
                        <div><h3 class="font-semibold text-slate-950">{{ $principle['title'] }}</h3><p class="mt-3 max-w-2xl leading-8 text-slate-600">{{ $principle['copy'] }}</p></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="overflow-hidden bg-slate-50 py-16 sm:py-24">
        <div class="public-container-wide">
            <div class="mb-10 grid items-end gap-6 md:grid-cols-2">
                <div><p class="public-kicker">One relationship, many perspectives</p><h2 class="mt-4 text-4xl font-semibold tracking-[-.05em] text-slate-950 sm:text-5xl">Real product surfaces, working together.</h2></div>
                <p class="max-w-xl leading-8 text-slate-600 md:justify-self-end">Members see progress. Trainers see coaching context. Operators see the work that keeps a gym healthy. Atlas connects those perspectives without pretending they are the same job.</p>
            </div>
            <figure class="atlas-editorial-art"><img src="{{ asset('images/public-site/editorial/gym-operations-team.webp') }}" width="1800" height="900" alt="Gym team using Atlas to coordinate member operations" loading="lazy"><figcaption>Members and verified trainers can begin independently. When they connect with a gym, each role keeps a purpose-built perspective while sharing the right operational context.</figcaption></figure>
            <div class="atlas-visual-ribbon">
                @foreach ([['product/member/dashboard-720.webp','Member progress dashboard'],['product/trainer/clients-720.webp','Trainer client overview'],['product/member/workouts-720.webp','Member workout plan'],['product/trainer/workout-builder-720.webp','Trainer workout builder']] as $visual)
                    <figure><img src="{{ asset('images/'.$visual[0]) }}" width="720" height="1280" alt="{{ $visual[1] }}" loading="lazy"></figure>
                @endforeach
            </div>
        </div>
    </section>

    <section class="public-section bg-white"><div class="public-container"><x-public.cta-section eyebrow="See how it connects" title="Follow the Atlas journey from discovery to daily progress." copy="Explore the product surfaces and understand what each person can do in the current ecosystem." primary-label="Explore Atlas" :primary-href="url('/product')" secondary-label="How Atlas works" :secondary-href="url('/how-it-works')" /></div></section>
</x-public.layouts.app>
