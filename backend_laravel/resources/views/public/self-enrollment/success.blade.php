<x-public.layouts.app :page-title="'Welcome to '.$link->gym->name" robots="noindex, nofollow">
    <section class="min-h-[70vh] bg-slate-950 pb-20 pt-40 text-white">
        <div class="public-container max-w-3xl text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-400/15 text-4xl text-emerald-300"><i class="ti ti-check"></i></div>
            <p class="public-eyebrow mt-8">Enrollment complete</p>
            <h1 class="mt-5 text-4xl font-semibold tracking-[-.05em] sm:text-6xl">Welcome to {{ $link->gym->name }}.</h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-slate-300">Your Atlas member profile is ready and you have been added to the gym. Use the same email when signing in to the Member App—there is no second account to create.</p>
            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row"><a href="gymatlasmember:///join/{{ $link->token }}" class="public-button public-button-primary">Open Member App</a><a href="{{ route('public.member-app') }}" class="public-button border border-white/20 bg-white/10 text-white">Get the Member App</a></div>
        </div>
    </section>
</x-public.layouts.app>
