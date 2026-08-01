@php
    $inquiryType = in_array($inquiryType ?? 'user', ['user', 'gym', 'trainer', 'support'], true) ? $inquiryType : 'user';
    $supportEmail = $settings['support_email'] ?? null;
    $supportPhone = $settings['support_phone'] ?? null;
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.public_whatsapp.number', '+917451008842')) ?: '917451008842';
    $whatsappUrl = 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode('Hello Atlas, I would like help with app access or onboarding.');
    $inquiryOptions = [
        'user' => ['label' => 'Member or visitor', 'help' => 'Independent Member App access, gym discovery or account questions.'],
        'gym' => ['label' => 'Gym', 'help' => 'Onboarding, operations and public-listing questions.'],
        'trainer' => ['label' => 'Trainer', 'help' => 'Independent verification or gym-connected Trainer App access.'],
        'support' => ['label' => 'Support', 'help' => 'Technical, privacy, safety or account help.'],
    ];
@endphp

<x-public.layouts.app page-title="Contact Atlas" page-description="Send a structured Atlas enquiry for member help, gym onboarding, trainer access or account support.">
    <section class="atlas-contact-stage overflow-hidden pb-20 pt-36 sm:pb-28 sm:pt-44" aria-labelledby="contact-form-heading">
        <div class="public-container-wide relative grid gap-12 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
            <div class="lg:sticky lg:top-28">
                <p class="public-eyebrow">Atlas support desk</p>
                <h1 class="mt-6 text-4xl font-semibold leading-[.98] tracking-[-.055em] text-white sm:text-6xl">Reach the right team with the right context.</h1>
                <p class="mt-6 max-w-xl text-base leading-8 text-slate-300 sm:text-lg">Choose who you are and explain what you need. Your request stays categorized from the first message, so it reaches the right workflow.</p>
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="mt-7 flex items-center gap-4 rounded-2xl bg-[#25D366] p-4 text-white shadow-[0_18px_50px_rgba(37,211,102,.2)] transition hover:-translate-y-0.5 hover:bg-[#1fbd59] hover:text-white">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 text-xl" aria-hidden="true"><i class="ti ti-brand-whatsapp"></i></span>
                    <span><strong class="block">WhatsApp Atlas</strong><small class="mt-1 block text-white/85">Chat with us at +91 74510 08842</small></span><span class="ml-auto text-xl" aria-hidden="true">→</span>
                </a>
                <div class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    @foreach ($inquiryOptions as $value => $option)
                        <a href="{{ route('public.contact', ['inquiry_type' => $value]) }}" class="atlas-route-card {{ $inquiryType === $value ? 'ring-1 ring-cyan-300/60' : '' }}" @if ($inquiryType === $value) aria-current="page" @endif>
                            <span>{{ strtoupper(substr($option['label'], 0, 1)) }}</span><span><strong class="block text-sm text-white">{{ $option['label'] }}</strong><small class="mt-1 block leading-5 text-slate-400">{{ $option['help'] }}</small></span>
                        </a>
                    @endforeach
                </div>
                @if ($supportPhone || $supportEmail)
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 border-t border-white/10 pt-6 text-sm text-slate-300">
                        @if ($supportPhone)<a class="hover:text-cyan-300" href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}">{{ $supportPhone }}</a>@endif
                        @if ($supportEmail)<a class="break-all hover:text-cyan-300" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@endif
                    </div>
                @endif
            </div>

            <div>
                @if (session('success'))<div role="status" class="mb-6 rounded-2xl border border-emerald-300/30 bg-emerald-400/10 px-5 py-4 text-sm text-emerald-100">{{ session('success') }}</div>@endif
                @if ($errors->any())<div role="alert" class="mb-6 rounded-2xl border border-rose-300/30 bg-rose-400/10 px-5 py-4 text-sm text-rose-100"><p class="font-semibold">Please correct the highlighted contact fields.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                <div class="atlas-contact-form-shell p-6 sm:p-10">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-7">
                        <div><p class="public-kicker">Structured enquiry</p><h2 id="contact-form-heading" class="mt-3 text-3xl font-semibold tracking-[-.04em] text-slate-950">Tell us what you need.</h2></div>
                        <span class="rounded-full bg-brand-50 px-3 py-2 text-xs font-bold text-brand-700">{{ $inquiryOptions[$inquiryType]['label'] }}</span>
                    </div>
                    <form method="POST" action="{{ route('public.contact.store') }}" class="mt-7 space-y-5">
                        @csrf
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="atlas-field"><label for="name" class="mb-2 block text-sm font-semibold text-slate-800">Your name <span class="text-rose-600" aria-hidden="true">*</span></label><input id="name" name="name" type="text" value="{{ old('name') }}" class="form-control" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="contact_name_error" @enderror>@error('name')<p id="contact_name_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                            <div class="atlas-field"><label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address <span class="text-rose-600" aria-hidden="true">*</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="contact_email_error" @enderror>@error('email')<p id="contact_email_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="atlas-field"><label for="phone" class="mb-2 block text-sm font-semibold text-slate-800">Phone number <span class="font-normal text-slate-500">(optional)</span></label><input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="form-control" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="contact_phone_error" @enderror>@error('phone')<p id="contact_phone_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                            <div class="atlas-field"><label for="inquiry_type" class="mb-2 block text-sm font-semibold text-slate-800">Enquiry type <span class="text-rose-600" aria-hidden="true">*</span></label><select id="inquiry_type" name="inquiry_type" class="form-control" required @error('inquiry_type') aria-invalid="true" aria-describedby="contact_type_error" @enderror>@foreach ($inquiryOptions as $value => $option)<option value="{{ $value }}" @selected(old('inquiry_type', $inquiryType) === $value)>{{ $option['label'] }}</option>@endforeach</select>@error('inquiry_type')<p id="contact_type_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        </div>
                        <div class="atlas-field"><label for="message" class="mb-2 block text-sm font-semibold text-slate-800">How can we help? <span class="text-rose-600" aria-hidden="true">*</span></label><textarea id="message" name="message" rows="7" class="form-control" required @error('message') aria-invalid="true" aria-describedby="contact_message_error" @enderror>{{ old('message') }}</textarea>@error('message')<p id="contact_message_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between"><p class="max-w-sm text-xs leading-6 text-slate-500">Fields marked required help the team identify and respond to your request.</p><div class="flex flex-col gap-3 sm:flex-row"><a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="public-button bg-[#25D366] text-white hover:bg-[#1fbd59]">WhatsApp</a><button type="submit" class="public-button public-button-primary">Send message <span aria-hidden="true">→</span></button></div></div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
