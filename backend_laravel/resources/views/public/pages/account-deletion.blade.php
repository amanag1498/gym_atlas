@php
    $supportEmail = $settings['support_email'] ?? null;
    $accountLabel = match ($appType ?? 'account') { 'member' => 'Atlas Member', 'trainer' => 'Atlas Trainer', default => 'Atlas' };
    $steps = [
        ['Request review', 'The submitted details are recorded as a support enquiry for the team to review.'],
        ['Identify the account', 'Support may contact the submitted email if more information is needed to identify the relevant account.'],
        ['Explain next steps', 'The team will explain the removal steps and any record-handling requirements that apply to the request.'],
    ];
@endphp

<x-public.layouts.app page-title="Delete Atlas Account" page-description="Request deletion of an Atlas Member or Atlas Trainer account and associated personal data.">
    <section class="atlas-editorial-hero pb-20 pt-36 sm:pb-24 sm:pt-44">
        <div class="public-container grid items-end gap-10 lg:grid-cols-[1fr_.55fr]">
            <div><p class="public-eyebrow">Account controls</p><h1 class="mt-6 max-w-4xl text-4xl font-semibold leading-[.98] tracking-[-.055em] text-white sm:text-6xl">Delete your Atlas account.</h1><p class="mt-6 max-w-2xl text-base leading-8 text-slate-300 sm:text-lg">A structured, human-reviewed path for requesting removal of your Atlas Member or Atlas Trainer account.</p></div>
            <div class="rounded-[1.5rem] border border-white/10 bg-white/[.06] p-6 backdrop-blur-xl"><p class="text-xs font-bold uppercase tracking-[.16em] text-cyan-300">Important</p><p class="mt-3 text-sm leading-7 text-slate-300">Submitting this form creates a support request. It does not delete an account automatically.</p></div>
        </div>
    </section>

    <section class="public-section bg-[#f3f6fa]" aria-labelledby="deletion-form-heading">
        <div class="public-container-wide">
            @if (session('success'))<div role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800"><p class="font-semibold">Please correct the highlighted fields.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr] lg:items-start">
                <aside class="overflow-hidden rounded-[2rem] bg-slate-950 p-7 text-white shadow-[0_30px_80px_rgba(15,23,42,.18)] sm:p-10 lg:sticky lg:top-28">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-cyan-300">A clear review path</p><h2 class="mt-4 text-3xl font-semibold tracking-[-.04em] text-white">What happens after you submit.</h2><p class="mt-4 text-sm leading-7 text-slate-400">The process stays deliberate so support can identify the correct account and explain what applies.</p>
                    <ol class="atlas-deletion-path mt-9">@foreach ($steps as $step)<li class="atlas-deletion-step"><span>0{{ $loop->iteration }}</span><div><h3 class="font-semibold text-white">{{ $step[0] }}</h3><p class="mt-2 text-sm leading-6 text-slate-400">{{ $step[1] }}</p></div></li>@endforeach</ol>
                    @if ($supportEmail)<p class="mt-5 border-t border-white/10 pt-6 text-sm leading-7 text-slate-300">Prefer email? Contact <a href="mailto:{{ $supportEmail }}" class="break-all font-semibold text-cyan-300 hover:text-white">{{ $supportEmail }}</a>.</p>@endif
                </aside>

                <div class="atlas-contact-form-shell p-6 sm:p-10">
                    <div class="border-b border-slate-200 pb-7"><p class="public-kicker">Deletion request</p><h2 id="deletion-form-heading" class="mt-4 text-3xl font-semibold tracking-[-.04em] text-slate-950 sm:text-4xl">Identify the account to be deleted.</h2><p class="mt-3 max-w-xl text-sm leading-7 text-slate-600">Use the email connected to {{ $accountLabel }}. Additional context can help support find the right account.</p></div>
                    <form method="POST" action="{{ route('public.contact.store') }}" class="mt-7 space-y-5">
                        @csrf
                        <input type="hidden" name="inquiry_type" value="support"><input type="hidden" name="redirect_to" value="{{ route('public.account-deletion') }}">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="atlas-field"><label for="deletion_name" class="mb-2 block text-sm font-semibold text-slate-800">Full name <span class="text-rose-600" aria-hidden="true">*</span></label><input id="deletion_name" name="name" type="text" value="{{ old('name') }}" class="form-control" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="deletion_name_error" @enderror>@error('name')<p id="deletion_name_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                            <div class="atlas-field"><label for="deletion_email" class="mb-2 block text-sm font-semibold text-slate-800">Atlas account email <span class="text-rose-600" aria-hidden="true">*</span></label><input id="deletion_email" name="email" type="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="deletion_email_error" @enderror>@error('email')<p id="deletion_email_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        </div>
                        <div class="atlas-field"><label for="deletion_phone" class="mb-2 block text-sm font-semibold text-slate-800">Phone number <span class="font-normal text-slate-500">(optional)</span></label><input id="deletion_phone" name="phone" type="text" value="{{ old('phone') }}" class="form-control" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="deletion_phone_error" @enderror>@error('phone')<p id="deletion_phone_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div class="atlas-field"><label for="deletion_message" class="mb-2 block text-sm font-semibold text-slate-800">Additional account details</label><textarea id="deletion_message" name="message" rows="6" class="form-control" required @error('message') aria-invalid="true" aria-describedby="deletion_message_error" @enderror>{{ old('message', "Please review my request to delete my {$accountLabel} account and tell me the next steps.") }}</textarea>@error('message')<p id="deletion_message_error" class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between"><p class="max-w-sm text-xs leading-6 text-slate-500">Support may contact the submitted email to verify the account.</p><button type="submit" class="public-button public-button-primary">Submit deletion request</button></div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-public.layouts.app>
