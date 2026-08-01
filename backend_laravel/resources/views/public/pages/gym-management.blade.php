@php
    $modules = [
        ['Member lifecycle', 'Create or invite members, assign trainers, and keep every record connected to its branch context.', ['Profiles', 'Approvals', 'Assignments', 'Status controls']],
        ['Membership control', 'Handle enrollment changes without losing the history behind each decision.', ['Plans', 'Renewals', 'Freezes', 'Custom fees']],
        ['Payments and dues', 'Record collections against the correct membership and retain a clear payment trail.', ['Collections', 'Receipts', 'Dues', 'Payment history']],
        ['Attendance operations', 'Support manual and biometric check-ins with daily and member-level visibility.', ['Check-in', 'Biometric flow', 'Daily view', 'History']],
        ['People and branches', 'Organize locations, trainers, and staff with role-aware access and activation controls.', ['Branches', 'Trainers', 'Staff', 'Permissions']],
        ['Trials and growth', 'Move public interest through review, assignment, completion, and member conversion.', ['Trial inbox', 'Assignment', 'Follow-up', 'Conversion']],
        ['Reports and follow-up', 'See revenue, dues, attendance, memberships, leads, and actions that need attention.', ['Reports', 'Reminders', 'Announcements', 'Exports']],
        ['Public presence', 'Control the information members see while retaining settings and audit history.', ['Listing', 'Gym profile', 'Settings', 'Audit log']],
    ];
    $journey = [
        ['Capture demand', 'A public enquiry or trial arrives with gym and branch context.'],
        ['Convert cleanly', 'The team assigns responsibility and moves the person into the member workflow.'],
        ['Operate daily', 'Membership, attendance, payment, and trainer activity stay attached to one record.'],
        ['Act on evidence', 'Reports, reminders, dues, and audit history reveal the next action.'],
    ];
@endphp

<x-public.layouts.app page-title="Gym Management" page-description="Manage gym members, memberships, payments, attendance, trainers, branches, trials, reports, reminders, and public listings in one connected workspace.">
    <section class="ops-hero">
        <div class="public-container-wide ops-hero-grid">
            <div>
                <p class="public-eyebrow">Gym management</p>
                <h1 class="ops-title mt-6">Run the member relationship from first enquiry to renewal.</h1>
                <p class="ops-lede mt-6">One operational workspace for the front desk, membership team, trainers, collections, attendance, reporting, and public discovery.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('public.for-gyms') }}#lead-form" class="public-button public-button-primary">Register your gym</a>
                    <a href="{{ route('web.gym.login') }}" class="public-button border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">Open Gym Admin</a>
                </div>
                <div class="ops-proofline"><span>Gym-scoped records</span><span>Role-aware access</span><span>Traceable activity</span></div>
            </div>
            <div class="ops-window" aria-label="Code-rendered representation of the Gym Admin operating workspace">
                <div class="ops-window-bar"><div class="ops-window-dots"><i></i><i></i><i></i></div><span class="ops-window-label">Gym Admin · Daily operations</span><span class="ops-status">Workspace active</span></div>
                <div class="ops-workspace">
                    <div class="ops-rail" aria-hidden="true">@foreach (['ti-layout-dashboard','ti-users','ti-id','ti-scan','ti-cash-banknote','ti-report-analytics'] as $icon)<span><i class="ti {{ $icon }}"></i></span>@endforeach</div>
                    <div class="ops-canvas">
                        <div class="ops-canvas-head"><div><p class="ops-canvas-kicker">Operations overview</p><p class="ops-canvas-title">Today at your gym</p></div><span class="text-xs text-slate-400">Live modules</span></div>
                        <div class="ops-metrics"><div class="ops-metric"><small>Member records</small><strong>Connected</strong></div><div class="ops-metric"><small>Attendance</small><strong>Daily</strong></div><div class="ops-metric"><small>Collections</small><strong>Traceable</strong></div></div>
                        <div class="grid gap-3 pt-4 sm:grid-cols-[1.15fr_.85fr]">
                            <div class="rounded-xl border border-white/10 bg-white/[.035] p-3"><p class="text-[.65rem] font-semibold uppercase tracking-widest text-slate-400">Operational rhythm</p><div class="ops-bars" aria-hidden="true">@foreach ([48,68,52,82,64,91,72,86] as $height)<i style="height: {{ $height }}%"></i>@endforeach</div></div>
                            <div class="rounded-xl border border-white/10 bg-white/[.035] p-3"><p class="text-[.65rem] font-semibold uppercase tracking-widest text-slate-400">Needs attention</p><div class="ops-feed"><div class="ops-feed-row"><span class="ops-feed-icon"><i class="ti ti-target"></i></span><span>Trial follow-up</span><em>Queue</em></div><div class="ops-feed-row"><span class="ops-feed-icon"><i class="ti ti-receipt"></i></span><span>Dues review</span><em>Ledger</em></div><div class="ops-feed-row"><span class="ops-feed-icon"><i class="ti ti-user-check"></i></span><span>Renewals</span><em>Members</em></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ops-section bg-white">
        <div class="public-container-wide">
            <div class="grid gap-8 lg:grid-cols-[.7fr_1.3fr] lg:items-end"><div><p class="public-kicker">Operational architecture</p><h2 class="mt-4 text-4xl font-semibold tracking-[-.04em] text-slate-950 sm:text-5xl">Not eight disconnected tools. One member story.</h2></div><p class="max-w-2xl text-base leading-8 text-slate-600 lg:justify-self-end">Every module below is part of the current Gym Admin. Availability remains role-, permission-, and gym-scoped.</p></div>
            <div class="ops-index mt-12">
                @foreach ($modules as $module)
                    <article class="ops-module"><span class="ops-module-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $module[0] }}</h3><p>{{ $module[1] }}</p><div class="ops-tags">@foreach ($module[2] as $item)<span>{{ $item }}</span>@endforeach</div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ops-section bg-slate-50">
        <div class="public-container-wide">
            <div class="grid gap-10 lg:grid-cols-[.7fr_1.3fr]"><div><p class="public-kicker">One operating loop</p><h2 class="mt-4 text-4xl font-semibold tracking-[-.04em] text-slate-950">From public interest to accountable operations.</h2><p class="mt-5 leading-8 text-slate-600">Each handoff retains the context the next team needs.</p></div><ol class="ops-timeline mt-0">@foreach ($journey as $step)<li class="ops-timeline-step"><span class="ops-timeline-dot">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $step[0] }}</h3><p>{{ $step[1] }}</p></div></li>@endforeach</ol></div>
            <figure class="ops-editorial mt-12"><img src="{{ asset('images/public-site/editorial/gym-operations-team.webp') }}" width="1800" height="900" loading="lazy" alt="Gym operations team coordinating daily member service"><figcaption class="ops-editorial-caption"><span class="ops-editorial-chip"><i class="ti ti-users-group"></i> Operational clarity</span><h3 class="text-3xl font-semibold tracking-[-.035em]">Give every role the context to take the next action.</h3><p class="mt-4">Atlas supports the team around the member—not just the record on a screen.</p></figcaption></figure>
        </div>
    </section>

    <section class="ops-section bg-white"><div class="public-container"><x-public.cta-section eyebrow="Start with your current workflow" title="Bring your gym operations into one connected workspace." copy="Tell us how your gym currently manages members, collections, attendance, trainers, and trials." primary-label="Start gym onboarding" :primary-href="route('public.for-gyms').'#lead-form'" secondary-label="Explore pricing" :secondary-href="route('public.pricing')" /></div></section>
</x-public.layouts.app>
