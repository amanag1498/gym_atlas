<x-public.layouts.enrollment :page-title="'Manage booking · '.$event->title" :social-image="$event->cover_image_url ?: $event->gym?->logo_url">
    <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-10 text-white">
        <section class="w-full max-w-xl rounded-[2rem] border border-white/10 bg-white/[.07] p-7 shadow-2xl sm:p-10">
            @if(session('booking_created'))
                <div class="mb-6 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-5 text-emerald-100">
                    <p class="font-bold">{{ $booking->status === 'reserved' ? 'Your place is confirmed.' : 'You joined the waitlist.' }}</p>
                    <p class="mt-1 text-sm text-emerald-200/80">Save this page to manage your booking, or add it to your Atlas account.</p>
                    <a href="{{ route('public.events.show', ['publicToken' => $event->public_token, 'claim' => $manageToken]) }}" class="mt-4 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-950">Save in Atlas app</a>
                </div>
            @endif
            @if(session('status'))<div class="mb-6 rounded-2xl border border-emerald-300/20 bg-emerald-400/10 p-4 text-sm font-semibold text-emerald-200">{{ session('status') }}</div>@endif
            <p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-300">Your event booking</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $event->title }}</h1>
            <dl class="mt-7 space-y-4 rounded-2xl bg-white/[.06] p-5 text-sm"><div class="flex justify-between gap-4"><dt class="text-slate-400">Attendee</dt><dd class="font-semibold text-right">{{ $booking->attendee_name }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">Status</dt><dd class="font-semibold capitalize">{{ str_replace('_', ' ', $booking->status) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-slate-400">When</dt><dd class="font-semibold text-right">{{ $event->starts_at->timezone($event->timezone)->format('D, j M Y · g:i A') }}</dd></div></dl>
            @if(in_array($booking->status, ['reserved', 'waitlisted'], true))
                <form method="POST" action="{{ route('public.events.cancel', [$event->public_token, $booking, $manageToken]) }}" class="mt-6">@csrf<button class="w-full rounded-xl border border-red-300/30 bg-red-500/10 px-5 py-3 font-bold text-red-200">Cancel booking</button></form>
            @endif
            <a href="{{ route('public.events.show', $event->public_token) }}" class="mt-4 block text-center text-sm font-semibold text-slate-300">Back to event</a>
        </section>
    </div>
</x-public.layouts.enrollment>
