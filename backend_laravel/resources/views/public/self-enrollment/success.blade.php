<x-public.layouts.enrollment
    :page-title="'Welcome to '.$link->gym->name"
    :page-description="'Your membership profile for '.$link->gym->name.' is ready.'"
    :social-image="$link->gym->logo_url"
>
    <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10 text-white">
        <section class="w-full max-w-xl overflow-hidden rounded-[2rem] border border-white/10 bg-white/[.07] p-6 text-center shadow-2xl backdrop-blur-xl sm:p-10">
            @if($link->gym->logo_url)
                <img src="{{ $link->gym->logo_url }}" alt="{{ $link->gym->name }} logo" class="mx-auto h-24 w-24 rounded-[1.75rem] border-4 border-white/15 bg-white object-cover">
            @else
                <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-[1.75rem] border border-white/15 bg-white/10 text-4xl text-teal-300"><i class="ti ti-building-store"></i></div>
            @endif
            <div class="mx-auto mt-6 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-400/15 text-2xl text-emerald-300"><i class="ti ti-check"></i></div>
            <p class="mt-5 text-xs font-bold uppercase tracking-[.22em] text-emerald-300">Enrollment complete</p>
            <h1 class="mt-3 text-3xl font-bold tracking-[-.04em] sm:text-4xl">Welcome to {{ $link->gym->name }}</h1>
            <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-slate-300">Your gym member profile is ready. Use the same email to sign in to the Member App—there is no second account to create.</p>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row"><a href="gymatlasmember:///join/{{ $link->token }}" class="public-button public-button-primary justify-center">Open Member App</a><a href="{{ route('public.member-app') }}" class="public-button justify-center border border-white/20 bg-white/10 text-white">Get the Member App</a></div>
            <div class="mt-8 flex items-center justify-center gap-2 border-t border-white/10 pt-6 text-xs text-slate-400"><img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-5 w-5 rounded-md"><span>Powered by Gym Atlas</span></div>
        </section>
    </div>
</x-public.layouts.enrollment>
