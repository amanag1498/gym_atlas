@php
    $supportEmail = $settings['support_email'] ?? null;
    $accountLabel = match ($appType ?? 'account') {
        'member' => 'Atlas Member',
        'trainer' => 'Atlas Trainer',
        default => 'Atlas',
    };
@endphp

<x-public.layouts.app page-title="Delete Atlas Account" page-description="Request deletion of an Atlas Member or Atlas Trainer account and associated personal data.">
    <section class="hero-wrap hero-wrap-2" style="background-color: #0f172a; min-height: 26rem;">
        <div class="container">
            <div class="row no-gutters align-items-end" style="min-height: 26rem; padding-top: 8rem; padding-bottom: 4rem;">
                <div class="col-xl-8 col-lg-10">
                    <div class="public-kicker mb-3" style="color: #bfdbfe !important;">Account controls</div>
                    <h1 class="mb-3 text-white" style="font-size: clamp(2.8rem, 5.5vw, 5rem); line-height: 0.98;">Delete your Atlas account.</h1>
                    <p class="atlas-hero-copy mb-0">Submit the email used with Atlas Member or Atlas Trainer so the team can verify ownership and process the request.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="ftco-section bg-light">
        <div class="container">
            @if (session('success'))
                <div class="mb-4 px-4 py-4 atlas-alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 px-4 py-4 atlas-alert-danger">
                    <div style="font-weight: 800;">Please correct the highlighted fields.</div>
                    <ul class="mt-3 mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row align-items-start">
                <div class="col-lg-5 mb-4 mb-lg-0">
                    <div class="atlas-card p-4 p-md-5 h-100">
                        <div class="public-kicker mb-3">What happens next</div>
                        <h2 class="mb-4" style="font-size: 1.9rem; font-weight: 800; color: #0f172a;">A verified deletion request removes account access and personal profile data.</h2>
                        <div style="color: #475569; line-height: 1.85; display: grid; gap: 1rem;">
                            <p class="mb-0">The team will contact you at the submitted email to verify that you control the account.</p>
                            <p class="mb-0">Member or trainer profile data and active authentication access are removed after verification.</p>
                            <p class="mb-0">Records required for fraud prevention, financial reconciliation, safety, or legal compliance may be retained in restricted systems only for the required period.</p>
                            @if ($supportEmail)
                                <p class="mb-0">You can also contact <a href="mailto:{{ $supportEmail }}" class="atlas-link">{{ $supportEmail }}</a>.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 offset-lg-1">
                    <div class="atlas-card p-4 p-md-5">
                        <div class="public-kicker mb-3">Deletion request</div>
                        <form method="POST" action="{{ route('public.contact.store') }}" class="contact-form">
                            @csrf
                            <input type="hidden" name="inquiry_type" value="support">
                            <input type="hidden" name="redirect_to" value="{{ route('public.account-deletion') }}">
                            <div class="form-group">
                                <input name="name" type="text" value="{{ old('name') }}" class="form-control" placeholder="Your full name">
                            </div>
                            <div class="form-group">
                                <input name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="Atlas account email">
                            </div>
                            <div class="form-group">
                                <input name="phone" type="text" value="{{ old('phone') }}" class="form-control" placeholder="Phone number (optional)">
                            </div>
                            <div class="form-group">
                                <textarea name="message" rows="5" class="form-control" placeholder="Add any details that help us identify the account">{{ old('message', "Please delete my {$accountLabel} account and associated personal data. I understand that legally required records may be retained for the required period.") }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary py-3 px-5">Submit deletion request</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
