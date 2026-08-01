@php
    $supportEmail = $settings['support_email'] ?? null;
    $externalUrl = $settings['privacy_policy_url'] ?? null;
    $sections = [
        ['id' => 'information', 'title' => 'Information used to provide Atlas', 'copy' => 'Atlas collects account and profile details, authentication identifiers, gym memberships, trainer relationships, workout and diet-plan activity, attendance, progress records, messages, notification preferences and support requests needed to provide the member and trainer apps.'],
        ['id' => 'safety', 'title' => 'Messaging safety records', 'copy' => 'Chat safety records can include acceptance of messaging rules, user blocks, reports, selected report reasons and any message linked to a report. Authorized personnel may review reported conversations and related account information to investigate abuse, enforce the Terms, protect users and meet legal obligations.'],
        ['id' => 'health-location', 'title' => 'Health, activity and location permissions', 'copy' => 'With a member’s permission, Atlas Member can read step count, walking or running distance, active calories and approximate or precise location. Health information is used for the member’s activity dashboard and sync, while location is used to find nearby gyms. Atlas does not sell health or location information.'],
        ['id' => 'uploads', 'title' => 'Photos and documents', 'copy' => 'Photos or documents selected through the system picker are uploaded only for user-requested profile, progress or trainer-certification features. Atlas does not request broad access to the device photo library.'],
        ['id' => 'visibility', 'title' => 'Public and private information', 'copy' => 'Public pages expose only intentionally published gym profile data. Private member, trainer, billing and gym-operations data stay outside public discovery surfaces. Gym listing controls determine pricing visibility, contact visibility and trial availability on the public website.'],
        ['id' => 'contact-data', 'title' => 'Contact and support requests', 'copy' => 'Contact submissions are stored so the team can respond to user, gym, trainer or support enquiries and keep follow-up context tied to the original request.'],
    ];
@endphp

<x-public.layouts.app page-title="Privacy Policy" page-description="An overview of how Atlas handles public discovery, account, fitness, messaging, location, enquiry and operational data.">
    <section class="atlas-document-hero pb-16 pt-36 sm:pb-20 sm:pt-44">
        <div class="public-container relative z-10"><p class="public-kicker">Trust centre / Privacy</p><h1 class="mt-5 max-w-4xl text-4xl font-semibold leading-[1] tracking-[-.055em] text-slate-950 sm:text-6xl">Privacy principles for the Atlas ecosystem.</h1><p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">A human-readable overview of how information moves across discovery, member and trainer workflows, safety and account controls.</p><div class="atlas-document-meta mt-8"><span>Plain-language overview</span><span>7 policy areas</span><span>Public and authenticated surfaces</span></div></div>
    </section>
    <section class="public-section bg-[#f3f6fa]">
        <div class="public-container atlas-document-layout">
            <aside class="atlas-document-nav"><p class="public-kicker">On this page</p><nav class="mt-4" aria-label="Privacy policy sections"><ul>@foreach ($sections as $section)<li><a href="#{{ $section['id'] }}"><span>0{{ $loop->iteration }}</span>{{ $section['title'] }}</a></li>@endforeach<li><a href="#deletion"><span>07</span>Deletion and retention</a></li></ul></nav><div class="mt-7 rounded-2xl bg-slate-950 p-5 text-sm leading-7 text-slate-300"><strong class="block text-white">Your control matters</strong><span class="mt-2 block">Public visibility is intentional. Private operations stay protected by account and role boundaries.</span></div></aside>
            <article class="atlas-document-paper">
                <header class="atlas-document-intro"><p class="public-kicker">Data handling</p><h2 class="mt-4 text-3xl font-semibold tracking-[-.045em] text-slate-950 sm:text-4xl">What Atlas uses—and why.</h2><p class="mt-5 max-w-2xl leading-8 text-slate-600">This page describes verified product behavior in readable form. It does not invent uses for information outside the current Atlas ecosystem.</p></header>
                @foreach ($sections as $section)<section id="{{ $section['id'] }}" class="atlas-document-clause"><span class="atlas-document-number">0{{ $loop->iteration }}</span><div><h3>{{ $section['title'] }}</h3><p>{{ $section['copy'] }}</p></div></section>@endforeach
                <section id="deletion" class="atlas-document-clause"><span class="atlas-document-number">07</span><div><h3>Account-removal requests</h3><p>Members and trainers can send an account-removal request through the <a href="{{ route('public.account-deletion') }}" class="atlas-inline-action font-semibold text-brand-600 hover:text-brand-700">account deletion page</a>. The form creates a support enquiry; the team reviews the request, may ask for information needed to identify the account, and explains the removal and record-handling steps that apply.</p></div></section>
                @if ($supportEmail)<div class="atlas-document-callout">Privacy questions can be directed to <a href="mailto:{{ $supportEmail }}" class="break-all font-semibold text-brand-600">{{ $supportEmail }}</a>.</div>@endif
                @if ($externalUrl)<div class="atlas-document-callout">The configured production privacy policy is also available at <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="break-all font-semibold text-brand-600">{{ $externalUrl }}</a>.</div>@endif
            </article>
        </div>
    </section>
</x-public.layouts.app>
