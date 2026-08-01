@php
    $gymLoginHref = Route::has('web.gym.login') ? route('web.gym.login') : null;
    $formRedirect = route('public.for-gyms').'#lead-form';

    $modules = [
        ['icon' => 'ti-users', 'title' => 'Members and memberships', 'copy' => 'Create or invite members, manage status, assign plans and trainers, and handle renewals, freezes, reactivation, extensions, cancellations and approved custom fees.'],
        ['icon' => 'ti-cash-banknote', 'title' => 'Payments and dues', 'copy' => 'Record collections, review outstanding dues, issue invoices and receipts, and retain reversible ledger history for operational accountability.'],
        ['icon' => 'ti-scan', 'title' => 'Attendance', 'copy' => 'Track daily and historical attendance through manual and supported biometric workflows, including correction review and exports.'],
        ['icon' => 'ti-building-community', 'title' => 'Branches, trainers and staff', 'copy' => 'Organize locations and teams, control active status and permissions, and keep trainer assignments tied to the right members.'],
        ['icon' => 'ti-target', 'title' => 'Trials and leads', 'copy' => 'Receive public trial requests, assign follow-up, update status and move completed trials into the member workflow.'],
        ['icon' => 'ti-report-analytics', 'title' => 'Reports and reminders', 'copy' => 'Review revenue, payments, dues, memberships, attendance, trainers, custom fees and leads, with CSV exports where available.'],
        ['icon' => 'ti-world', 'title' => 'Public gym presence', 'copy' => 'Control profile details, listing visibility, published pricing, contact information and trial availability shown in Atlas discovery.'],
        ['icon' => 'ti-shield-check', 'title' => 'Settings and audit history', 'copy' => 'Keep important operational changes traceable through role-aware access, gym settings, notifications and audit logs.'],
    ];

    $workflow = [
        ['step' => '01', 'title' => 'Publish', 'copy' => 'Configure the gym profile, branches, facilities and the information members can see.'],
        ['step' => '02', 'title' => 'Capture', 'copy' => 'Receive structured enquiries and trial requests from public discovery.'],
        ['step' => '03', 'title' => 'Convert', 'copy' => 'Assign responsibility, follow up and move eligible prospects into membership.'],
        ['step' => '04', 'title' => 'Operate', 'copy' => 'Manage attendance, collections, coaching context, renewals and reporting around one member record.'],
    ];
@endphp

<x-public.layouts.app page-title="For Gyms" page-description="Connect gym discovery, trials, memberships, attendance, payments, branches, trainers, reports and public listing control with Atlas Gym Management.">
    <section class="ops-hero">
        <div class="public-container-wide ops-hero-grid">
            <div>
                <p class="public-eyebrow">For gym operators</p>
                <h1 class="ops-title mt-6">Run the gym from the same ecosystem that brings members in.</h1>
                <p class="ops-lede mt-6">Atlas connects discovery with trials, memberships, collections, attendance, branches, trainers, and reporting—so context survives every handoff.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="#lead-form" class="public-button public-button-primary">Register your gym</a>
                    @if ($gymLoginHref)
                        <a href="{{ $gymLoginHref }}" class="public-button border border-white/20 bg-white/10 text-white hover:bg-white/15 hover:text-white">Gym admin login</a>
                    @endif
                </div>
                <div class="ops-proofline"><span>Built for operating teams</span><span>Connected member record</span><span>Branch-aware access</span></div>
            </div>
            <aside class="ops-window" aria-label="Code-rendered representation of the gym onboarding pipeline">
                <div class="ops-window-bar"><div class="ops-window-dots"><i></i><i></i><i></i></div><span class="ops-window-label">Gym growth · Operating loop</span><span class="ops-status">Connected</span></div>
                <div class="p-5 sm:p-7">
                    <p class="ops-canvas-kicker">Member journey</p><p class="ops-canvas-title">From interest to retention</p>
                    <div class="mt-6 space-y-3">@foreach ([['ti-world','Discovery','Build trust publicly'],['ti-target','Trial inbox','Own the follow-up'],['ti-user-check','Membership','Convert with context'],['ti-scan','Daily operations','Serve consistently'],['ti-chart-line','Reports','Act on evidence']] as $item)<div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 rounded-xl border border-white/10 bg-white/[.04] p-3"><span class="ops-feed-icon"><i class="ti {{ $item[0] }}"></i></span><div><p class="text-sm font-semibold text-white">{{ $item[1] }}</p><p class="mt-1 text-[.7rem] text-slate-400">{{ $item[2] }}</p></div><i class="ti ti-arrow-right text-slate-500"></i></div>@endforeach</div>
                    <p class="mt-5 text-xs leading-6 text-slate-400">Payments are recorded and tracked; Atlas does not currently promise a member-facing payment gateway.</p>
                </div>
            </aside>
        </div>
    </section>

    <section class="ops-section bg-slate-50">
        <div class="public-container-wide">
            <figure class="ops-editorial">
                <img src="{{ asset('images/public-site/editorial/gym-operations-team.webp') }}" width="1800" height="900" loading="lazy" decoding="async" alt="Gym operations team coordinating member service at a modern front desk">
                <figcaption class="ops-editorial-caption"><span class="ops-editorial-chip"><i class="ti ti-building-community"></i> The people behind the system</span><h2 class="text-3xl font-semibold tracking-[-.035em] sm:text-4xl">Software should help the team move together.</h2><p class="mt-4">Front desk, membership, coaching, and management workflows remain connected to the same operational context.</p></figcaption>
            </figure>
        </div>
    </section>

    <section class="ops-section bg-white" aria-labelledby="gym-capabilities-heading">
        <div class="public-container-wide">
            <div class="grid gap-8 lg:grid-cols-[.75fr_1.25fr] lg:items-end">
              <div class="public-section-heading">
                <p class="public-kicker">Current capabilities</p>
                <h2 id="gym-capabilities-heading" class="mt-4">The daily workflows operators need, explained clearly.</h2>
              </div><p class="max-w-2xl leading-8 text-slate-600 lg:justify-self-end">These capabilities are implemented in the current Gym Admin. Availability can vary with role, permission, and gym configuration.</p></div>
            <div class="ops-index mt-12">
                @foreach ($modules as $module)
                    <article class="ops-module"><span class="ops-module-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h3>{{ $module['title'] }}</h3><p>{{ $module['copy'] }}</p><div class="ops-tags"><span><i class="ti {{ $module['icon'] }} mr-1"></i> Gym Admin module</span></div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ops-section bg-slate-50" aria-labelledby="gym-workflow-heading">
        <div class="public-container-wide">
            <div class="public-section-heading">
                <p class="public-kicker">From discovery to retention</p>
                <h2 id="gym-workflow-heading" class="mt-4">Turn public interest into accountable operations.</h2>
                <p class="mt-5">A trial request is submitted for gym review; it is not an instantly confirmed booking.</p>
            </div>
            <ol class="ops-timeline">
                @foreach ($workflow as $step)
                    <li class="ops-timeline-step"><span class="ops-timeline-dot">{{ $step['step'] }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['copy'] }}</p></div></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section id="lead-form" class="ops-section bg-white" aria-labelledby="gym-inquiry-heading">
        <div class="public-container ops-form-shell">
            <div class="ops-form-intro">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-300">Start onboarding</p>
                <h2 id="gym-inquiry-heading" class="mt-4 text-3xl font-semibold tracking-[-0.035em] text-white sm:text-4xl">Tell us how your gym operates today.</h2>
                <p class="mt-5 leading-7 text-slate-300">Share your branch setup, member workflow and operational needs. This form creates a real gym enquiry for the Atlas team to review.</p>
                <ul class="mt-7 space-y-3 text-sm leading-6 text-slate-300">
                    <li>• Structured gym-onboarding enquiry</li>
                    <li>• Space to explain current workflow gaps</li>
                    <li>• Existing operators can use Gym Admin login</li>
                </ul>
            </div>
            <div class="ops-form-body">
                @if (session('success'))
                    <div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <p class="font-semibold">Please correct the highlighted gym enquiry fields.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('public.contact.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="inquiry_type" value="gym">
                    <input type="hidden" name="redirect_to" value="{{ $formRedirect }}">
                    <div>
                        <label for="gym_name" class="mb-2 block text-sm font-semibold text-slate-800">Gym or owner name <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <input id="gym_name" name="name" value="{{ old('name') }}" class="form-control @error('name') border-rose-400 @enderror" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="gym_name_error" @enderror>
                        @error('name')<p id="gym_name_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="gym_email" class="mb-2 block text-sm font-semibold text-slate-800">Email address <span class="text-rose-600" aria-hidden="true">*</span></label>
                            <input id="gym_email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="gym_email_error" @enderror>
                            @error('email')<p id="gym_email_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gym_phone" class="mb-2 block text-sm font-semibold text-slate-800">Phone number <span class="font-normal text-slate-500">(optional)</span></label>
                            <input id="gym_phone" name="phone" value="{{ old('phone') }}" class="form-control" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="gym_phone_error" @enderror>
                            @error('phone')<p id="gym_phone_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="gym_message" class="mb-2 block text-sm font-semibold text-slate-800">How can Atlas help? <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="gym_message" name="message" rows="6" class="form-control" required @error('message') aria-invalid="true" aria-describedby="gym_message_error" @enderror>{{ old('message', 'I want to onboard my gym onto the platform and understand how Atlas can support our operations.') }}</textarea>
                        @error('message')<p id="gym_message_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="public-button public-button-primary">Submit gym enquiry</button>
                </form>
            </div>
        </div>
    </section>
</x-public.layouts.app>
