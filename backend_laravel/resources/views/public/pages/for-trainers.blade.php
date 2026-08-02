@php
    $formRedirect = route('public.for-trainers').'#trainer-access';
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.public_whatsapp.number'));
    $whatsappHref = $whatsappNumber !== ''
        ? 'https://wa.me/'.$whatsappNumber.'?text='.urlencode('Hello Atlas, I am a trainer and would like help with account access and verification.')
        : null;
    $capabilities = [
        ['icon' => 'ti-dashboard', 'title' => 'Daily coaching view', 'copy' => 'Review today’s client queue, assigned members, alerts and the work that needs follow-up.'],
        ['icon' => 'ti-activity', 'title' => 'Workout programming', 'copy' => 'Build member plans, reuse workout templates, search the exercise library and preview programs before assignment.'],
        ['icon' => 'ti-apple', 'title' => 'Meal-based diet plans', 'copy' => 'Create and assign structured diet plans and reusable templates when your verified account or gym-granted access permits it.'],
        ['icon' => 'ti-chart-line', 'title' => 'Member context', 'copy' => 'Review available attendance, progress, workout history and member details before deciding the next coaching action.'],
        ['icon' => 'ti-notes', 'title' => 'Notes and follow-ups', 'copy' => 'Create and update member notes, track pending work and mark follow-up actions complete.'],
        ['icon' => 'ti-message-circle', 'title' => 'Connected messaging', 'copy' => 'Keep assigned-member conversations in a persistent inbox with history, read state and safety controls. Live delivery updates depend on the configured realtime service.'],
        ['icon' => 'ti-target', 'title' => 'Trials and invitations', 'copy' => 'Follow up on trial leads assigned by the gym and invite prospective members through the gym approval workflow.'],
        ['icon' => 'ti-certificate', 'title' => 'Trainer identity', 'copy' => 'Maintain a trainer profile and certifications while receiving relevant announcements, notifications and alerts.'],
    ];
@endphp

<x-public.layouts.app page-title="For Trainers" page-description="A coaching workspace for verified independent and gym-connected trainers, with member plans, progress context, follow-ups, messaging and alerts.">
    <section class="ops-hero">
        <div class="public-container-wide ops-hero-grid">
            <div>
                <p class="public-eyebrow">For independent and gym-connected trainers</p>
                <h1 class="ops-title mt-6">Coach with the member context already in view.</h1>
                <p class="ops-lede mt-6">Assigned clients, plans, progress context, follow-ups, trial work, alerts, and conversations—organized around the coaching day.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="#trainer-access" class="public-button public-button-primary">Request trainer access</a>
                    @if (Route::has('public.trainer-app'))
                        <a href="{{ route('public.trainer-app') }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15 hover:text-white">Explore the Trainer App</a>
                    @endif
                </div>
                <div class="ops-proofline"><span>Independent or gym-connected</span><span>Verified member access</span><span>Permission-aware tools</span></div>
            </div>
            <aside class="ops-window" aria-label="Code-rendered representation of the trainer coaching queue">
                <div class="ops-window-bar"><div class="ops-window-dots"><i></i><i></i><i></i></div><span class="ops-window-label">Trainer workspace · Today</span><span class="ops-status">Ready</span></div>
                <div class="p-5 sm:p-7"><div class="ops-canvas-head"><div><p class="ops-canvas-kicker">Coaching queue</p><p class="ops-canvas-title">A practical coaching day</p></div><span class="rounded-full bg-brand-500/15 px-3 py-1 text-[.68rem] font-bold text-indigo-300">Verified access</span></div><ol class="mt-6 space-y-3">@foreach ([['ti-bell','Review clients and alerts','Prioritize'],['ti-user-search','Open member context','Understand'],['ti-barbell','Build or update the plan','Program'],['ti-notes','Record the follow-up','Document'],['ti-message-circle','Continue the conversation','Support']] as $step)<li class="grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] p-3"><span class="ops-feed-icon"><i class="ti {{ $step[0] }}"></i></span><span class="text-sm font-semibold text-white">{{ $step[1] }}</span><em class="text-[.68rem] not-italic text-slate-500">{{ $step[2] }}</em></li>@endforeach</ol></div>
            </aside>
        </div>
    </section>

    <section class="ops-section bg-white" aria-labelledby="trainer-capabilities-heading">
        <div class="public-container-wide">
            <div class="grid gap-8 lg:grid-cols-[.75fr_1.25fr] lg:items-end"><div class="public-section-heading">
                <p class="public-kicker">Current capabilities</p>
                <h2 id="trainer-capabilities-heading" class="mt-4">The real coaching workflow, not a generic feature list.</h2>
            </div><p class="max-w-2xl leading-8 text-slate-600 lg:justify-self-end">Platform verification unlocks personal member invitations for any trainer. Gym-connected trainers keep their gym, branch, assigned members, role and permission boundaries alongside that personal coaching scope.</p></div>
            <div class="ops-index mt-12">
                @foreach ($capabilities as $capability)
                    <article class="ops-module"><span class="ops-module-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h3>{{ $capability['title'] }}</h3><p>{{ $capability['copy'] }}</p><div class="ops-tags"><span><i class="ti {{ $capability['icon'] }} mr-1"></i> Trainer workflow</span></div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ops-section bg-slate-50" aria-labelledby="trainer-scope-heading">
        <div class="public-container-wide grid gap-8 lg:grid-cols-[.72fr_1.28fr] lg:items-center">
            <div>
                <p class="public-kicker">Current product scope</p>
                <h2 id="trainer-scope-heading" class="mt-4 text-3xl font-semibold tracking-[-0.035em] text-slate-950">Choose the access path that matches how you coach.</h2>
            </div>
            <figure class="ops-editorial"><img src="{{ asset('images/public-site/editorial/trainer-member-coaching.webp') }}" width="1800" height="948" loading="lazy" alt="Personal trainer coaching a gym member with attentive, hands-on guidance"><figcaption class="ops-editorial-caption"><span class="ops-editorial-chip"><i class="ti ti-heart-rate-monitor"></i> Human coaching, informed</span><p>A verified trainer can coach personal clients while continuing to serve members assigned by a gym. The two relationships and their data remain separate. Atlas is not a public trainer marketplace.</p><p class="mt-2 text-xs">Paid online coaching, live video or voice sessions, and AI-generated programs are not part of the current product promise.</p></figcaption></figure>
        </div>
    </section>

    <section id="trainer-access" class="ops-section bg-white" aria-labelledby="trainer-access-heading">
        <div class="public-container ops-form-shell">
            <div class="ops-form-intro">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Access path</p>
                <h2 id="trainer-access-heading" class="mt-4 text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl">Join independently or through your gym.</h2>
                <p class="mt-5 leading-7 text-slate-300">Tell the Atlas team how you coach. Independent trainers are guided through account verification; gym-connected trainers can confirm their gym relationship and access needs.</p>
                <p class="mt-6 text-sm leading-7 text-slate-400">Verification is required before an independent trainer can add members or manage member plans. This does not create a public marketplace profile.</p>
                @if ($whatsappHref)
                    <a href="{{ $whatsappHref }}" target="_blank" rel="noopener noreferrer" class="public-button mt-6 border border-white/20 bg-white/10 text-white hover:bg-white/15">Ask about access on WhatsApp</a>
                @endif
            </div>
            <div class="ops-form-body">
                @if (session('success'))
                    <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold">Please correct the highlighted trainer enquiry fields.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="inquiry_type" value="trainer">
                    <input type="hidden" name="redirect_to" value="{{ $formRedirect }}">
                    <div>
                        <label for="trainer_name" class="mb-2 block text-sm font-semibold text-slate-800">Full name <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <input id="trainer_name" name="name" value="{{ old('name') }}" class="form-control" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="trainer_name_error" @enderror>
                        @error('name')<p id="trainer_name_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="trainer_email" class="mb-2 block text-sm font-semibold text-slate-800">Email address <span class="text-rose-600" aria-hidden="true">*</span></label>
                            <input id="trainer_email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="trainer_email_error" @enderror>
                            @error('email')<p id="trainer_email_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="trainer_phone" class="mb-2 block text-sm font-semibold text-slate-800">Phone number <span class="font-normal text-slate-500">(optional)</span></label>
                            <input id="trainer_phone" name="phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="trainer_phone_error" @enderror>
                            @error('phone')<p id="trainer_phone_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="trainer_message" class="mb-2 block text-sm font-semibold text-slate-800">Access path and coaching needs <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="trainer_message" name="message" rows="6" class="form-control" required @error('message') aria-invalid="true" aria-describedby="trainer_message_error" @enderror>{{ old('message', 'I am joining independently / through a gym and would like help with trainer access.') }}</textarea>
                        @error('message')<p id="trainer_message_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="public-button public-button-primary">Submit trainer enquiry</button>
                </form>
            </div>
        </div>
    </section>
</x-public.layouts.app>
