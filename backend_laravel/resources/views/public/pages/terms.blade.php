@php
    $supportEmail = $settings['support_email'] ?? null;
    $externalUrl = $settings['terms_url'] ?? null;
    $sections = [
        ['id' => 'public-use', 'title' => 'Public website use', 'copy' => 'The public website is intended for gym discovery, enquiries and trial-lead creation across eligible listings on the platform. Use of Atlas must remain lawful, accurate and non-abusive across public and authenticated workflows.'],
        ['id' => 'gym-information', 'title' => 'Gym-published information', 'copy' => 'Gyms are responsible for the accuracy of the information they choose to publish publicly, including pricing visibility, facilities, contact visibility and trial availability.'],
        ['id' => 'requests', 'title' => 'Enquiries and trial requests', 'copy' => 'Submitting a contact or trial request does not guarantee acceptance, conversion, immediate onboarding or an instantly confirmed booking. Those decisions remain with the relevant gym and its operations team.'],
        ['id' => 'messaging', 'title' => 'Messaging and user content', 'copy' => 'Member-trainer chat is for legitimate fitness coaching. Users must not send harassment, threats, hate speech, sexual or exploitative material, spam, impersonation, unlawful content, dangerous instructions, or content that violates another person’s privacy or intellectual-property rights.'],
        ['id' => 'safety', 'title' => 'Reports, blocks and enforcement', 'copy' => 'Before sending chat content, users must accept the messaging rules in the app. Users can report a conversation and block the other participant. Atlas may review reports, restrict messaging, retain evidence needed for safety, suspend accounts and cooperate with lawful requests. Knowingly false or abusive reports are prohibited.'],
    ];
@endphp

<x-public.layouts.app page-title="Terms of Service" page-description="Terms overview for Atlas public discovery, enquiries, trial requests, authenticated fitness workflows and member-trainer messaging.">
    <section class="atlas-document-hero pb-16 pt-36 sm:pb-20 sm:pt-44">
        <div class="public-container relative z-10"><p class="public-kicker">Trust centre / Terms</p><h1 class="mt-5 max-w-4xl text-4xl font-semibold leading-[1] tracking-[-.055em] text-slate-950 sm:text-6xl">Use Atlas accurately, lawfully and with real intent.</h1><p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg">Clear expectations for public discovery, enquiries, trial requests and safe participation in authenticated fitness workflows.</p><div class="atlas-document-meta mt-8"><span>Plain-language overview</span><span>5 use principles</span><span>Safety and accountability</span></div></div>
    </section>
    <section class="public-section bg-[#f3f6fa]">
        <div class="public-container atlas-document-layout">
            <aside class="atlas-document-nav"><p class="public-kicker">On this page</p><nav class="mt-4" aria-label="Terms sections"><ul>@foreach ($sections as $section)<li><a href="#{{ $section['id'] }}"><span>0{{ $loop->iteration }}</span>{{ $section['title'] }}</a></li>@endforeach</ul></nav><div class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-7 text-slate-600"><strong class="block text-slate-950">The simple standard</strong><span class="mt-2 block">Use Atlas lawfully, accurately and without harming other participants.</span></div></aside>
            <article class="atlas-document-paper">
                <header class="atlas-document-intro"><p class="public-kicker">Public and product use</p><h2 class="mt-4 text-3xl font-semibold tracking-[-.045em] text-slate-950 sm:text-4xl">Clear expectations across discovery and coaching.</h2><p class="mt-5 max-w-2xl leading-8 text-slate-600">These principles explain the current public and authenticated workflows without overstating acceptance, booking or access guarantees.</p></header>
                @foreach ($sections as $section)<section id="{{ $section['id'] }}" class="atlas-document-clause"><span class="atlas-document-number">0{{ $loop->iteration }}</span><div><h3>{{ $section['title'] }}</h3><p>{{ $section['copy'] }}</p>@if ($section['id'] === 'safety')<p>Blocking stops new messages between participants until the person who initiated the block removes it. Reported content is reviewed by authorized Atlas or gym personnel and appropriate action should be taken promptly.</p>@endif</div></section>@endforeach
                @if ($supportEmail)<div class="atlas-document-callout">Questions about these terms can be raised via <a href="mailto:{{ $supportEmail }}" class="break-all font-semibold text-brand-600">{{ $supportEmail }}</a>.</div>@endif
                @if ($externalUrl)<div class="atlas-document-callout">The configured production terms are also available at <a href="{{ $externalUrl }}" target="_blank" rel="noopener noreferrer" class="break-all font-semibold text-brand-600">{{ $externalUrl }}</a>.</div>@endif
            </article>
        </div>
    </section>
</x-public.layouts.app>
