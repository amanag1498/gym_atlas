<x-public.layouts.enrollment
    :page-title="$event->title.' · '.($event->gym?->name ?: 'Gym Atlas')"
    :page-description="Str($event->description)->limit(150)"
    :social-image="$event->cover_image_url ?: $event->gym?->logo_url"
>
    @php
        $timezone = $event->timezone ?: 'UTC';
        $startsAt = $event->starts_at->copy()->timezone($timezone);
        $reserved = (int) $event->reserved_count;
        $available = $event->capacity === null ? null : max(0, $event->capacity - $reserved);
        $bookingOpen = $event->starts_at->isFuture()
            && (!$event->booking_opens_at || now()->gte($event->booking_opens_at))
            && (!$event->booking_closes_at || now()->lte($event->booking_closes_at));
        $full = $available === 0;
        $guestBookingAllowed = $event->booking_audience === 'anyone' && $event->public_booking_enabled;
    @endphp
    <div class="min-h-screen bg-slate-950 px-4 py-8 text-white sm:px-6 lg:py-12">
        <div class="mx-auto grid max-w-6xl overflow-hidden rounded-[2rem] border border-white/10 bg-white/[.06] shadow-2xl backdrop-blur lg:grid-cols-[1.05fr_.95fr]">
            <section class="relative min-h-[28rem] overflow-hidden p-7 sm:p-10">
                @if($event->cover_image_url)
                    <img src="{{ $event->cover_image_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-slate-950/25"></div>
                @endif
                <div class="relative flex h-full flex-col">
                    <div class="flex items-center gap-3">
                        @if($event->gym?->logo_url)
                            <img src="{{ $event->gym->logo_url }}" alt="{{ $event->gym->name }} logo" class="h-14 w-14 rounded-2xl border-2 border-white/15 bg-white object-cover">
                        @else
                            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500 text-2xl"><i class="ti ti-calendar-event"></i></span>
                        @endif
                        <div><p class="text-xs font-bold uppercase tracking-[.18em] text-indigo-300">Hosted by</p><p class="mt-1 font-semibold">{{ $event->gym?->name ?: 'Gym Atlas' }}</p></div>
                    </div>
                    <div class="mt-auto pt-14">
                        @if($event->category)<span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-indigo-200">{{ $event->category }}</span>@endif
                        <h1 class="mt-4 text-4xl font-bold tracking-[-.045em] sm:text-5xl">{{ $event->title }}</h1>
                        @if($event->description)<p class="mt-5 max-w-2xl whitespace-pre-line text-sm leading-7 text-slate-300 sm:text-base">{{ $event->description }}</p>@endif
                        <div class="mt-7 grid gap-3 text-sm sm:grid-cols-2">
                            <div class="rounded-2xl bg-white/[.07] p-4"><i class="ti ti-calendar text-indigo-300"></i><p class="mt-2 font-semibold">{{ $startsAt->format('D, j M Y') }}</p><p class="text-slate-400">{{ $startsAt->format('g:i A') }} · {{ $timezone }}</p></div>
                            <div class="rounded-2xl bg-white/[.07] p-4"><i class="ti ti-map-pin text-indigo-300"></i><p class="mt-2 font-semibold">{{ $event->location_name ?: 'Location shared by host' }}</p><p class="text-slate-400">{{ $event->address }}</p></div>
                        </div>
                    </div>
                    <div class="mt-8 flex items-center gap-2 text-xs text-slate-500"><img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-5 w-5 rounded-md"><span>Booking powered by Gym Atlas</span></div>
                </div>
            </section>

            <section class="bg-white p-7 text-slate-900 sm:p-10">
                @if(session('status'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
                <p class="text-xs font-bold uppercase tracking-[.18em] text-indigo-600">{{ $guestBookingAllowed ? 'Reserve your place' : 'Atlas member booking' }}</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight">{{ $guestBookingAllowed ? ($full && $event->waitlist_enabled ? 'Join the waitlist' : 'Book this event') : 'Open this event in the Member app' }}</h2>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-slate-100 px-3 py-2">{{ $event->pricing_type === 'free' ? 'Free entry' : $event->currency.' '.number_format((float) $event->price_amount, 2).' · pay at venue' }}</span>
                    @if($available !== null)<span class="rounded-full {{ $available > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-2">{{ $available > 0 ? $available.' places left' : ($event->waitlist_enabled ? 'Waitlist available' : 'Fully booked') }}</span>@endif
                </div>

                @if(! $guestBookingAllowed)
                    <div class="mt-7 rounded-2xl border border-indigo-100 bg-indigo-50 p-5 text-sm leading-6 text-indigo-900">This gym shared the event by link. Sign in to the Atlas Member app to reserve your place. It will not enroll you into the hosting gym.</div>
                @elseif($bookingOpen && (!$full || $event->waitlist_enabled))
                    <form method="POST" action="{{ route('public.events.book', $event->public_token) }}" class="mt-7 space-y-5">
                        @csrf
                        <input name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                        <label class="block"><span class="text-sm font-semibold">Full name</span><input name="name" value="{{ old('name') }}" required autocomplete="name" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">@error('name')<small class="mt-1 block text-red-600">{{ $message }}</small>@enderror</label>
                        <label class="block"><span class="text-sm font-semibold">Email</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">@error('email')<small class="mt-1 block text-red-600">{{ $message }}</small>@enderror</label>
                        <label class="block"><span class="text-sm font-semibold">Mobile number</span><input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">@error('phone')<small class="mt-1 block text-red-600">{{ $message }}</small>@enderror</label>
                        @foreach(($event->registration_form_schema ?? []) as $field)
                            @php($key = $field['key'] ?? null)
                            @if($key)
                                <label class="block"><span class="text-sm font-semibold">{{ $field['label'] ?? Str::headline($key) }} @if(!empty($field['required']))<span class="text-red-500">*</span>@endif</span>
                                    @if(($field['type'] ?? 'text') === 'textarea')
                                        <textarea name="answers[{{ $key }}]" @required(!empty($field['required'])) class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">{{ old('answers.'.$key) }}</textarea>
                                    @elseif(($field['type'] ?? 'text') === 'select')
                                        <select name="answers[{{ $key }}]" @required(!empty($field['required'])) class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3"><option value="">Select</option>@foreach(($field['options'] ?? []) as $option)<option value="{{ $option }}" @selected(old('answers.'.$key) === $option)>{{ $option }}</option>@endforeach</select>
                                    @else
                                        <input name="answers[{{ $key }}]" value="{{ old('answers.'.$key) }}" @required(!empty($field['required'])) class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3">
                                    @endif
                                    @error('answers.'.$key)<small class="mt-1 block text-red-600">{{ $message }}</small>@enderror
                                </label>
                            @endif
                        @endforeach
                        <button class="flex w-full items-center justify-center rounded-xl bg-indigo-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700">{{ $full ? 'Join waitlist' : 'Confirm booking' }}</button>
                        <p class="text-center text-xs leading-5 text-slate-500">No Atlas account or gym membership is required. Your details are used to manage this event booking.</p>
                    </form>
                @else
                    <div class="mt-7 rounded-2xl bg-slate-100 p-5 text-sm text-slate-600">{{ $full ? 'This event is fully booked.' : 'Booking is not currently open.' }}</div>
                @endif
                <a href="gymatlasmember:///events/{{ $event->public_token }}" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700"><i class="ti ti-device-mobile"></i> Open in Atlas Member App</a>
            </section>
        </div>
    </div>
</x-public.layouts.enrollment>
