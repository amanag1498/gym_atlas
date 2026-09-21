<x-public.layouts.enrollment
    :page-title="($snapshot['name'] ?? 'Shared workout plan').' · Gym Atlas'"
    :page-description="'Preview this workout plan and save a copy to your Gym Atlas account.'"
>
    @php
        $days = collect($snapshot['days'] ?? []);
        $exerciseCount = $days->sum(fn ($day) => count($day['exercises'] ?? []));
        $appUri = 'gymatlasmember:///workouts/shared/'.rawurlencode($token);
    @endphp
    <div class="min-h-screen bg-slate-950 px-4 py-8 text-white sm:px-6 lg:py-12">
        <main class="mx-auto grid max-w-6xl overflow-hidden rounded-[2rem] border border-white/10 bg-white/[.06] shadow-2xl backdrop-blur lg:grid-cols-[.9fr_1.1fr]">
            <section class="flex flex-col p-7 sm:p-10">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/public-site/brand/atlas-mark-64.png') }}" alt="" class="h-12 w-12 rounded-2xl">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-indigo-300">Shared workout</p>
                        <p class="mt-1 text-sm text-slate-400">From {{ $share->sharedBy?->name ?: 'an Atlas member' }}</p>
                    </div>
                </div>

                <div class="mt-12">
                    <h1 class="text-4xl font-bold tracking-[-.045em] sm:text-5xl">{{ $snapshot['name'] ?? 'Workout plan' }}</h1>
                    @if(!empty($snapshot['goal']))
                        <p class="mt-4 text-lg text-slate-300">Built for {{ Str::lower($snapshot['goal']) }}.</p>
                    @endif
                    <div class="mt-7 flex flex-wrap gap-2 text-sm font-semibold">
                        <span class="rounded-full bg-white/10 px-3 py-2">{{ $days->count() }} training {{ Str::plural('day', $days->count()) }}</span>
                        <span class="rounded-full bg-white/10 px-3 py-2">{{ $exerciseCount }} {{ Str::plural('exercise', $exerciseCount) }}</span>
                        @if(!empty($snapshot['duration_weeks']))
                            <span class="rounded-full bg-white/10 px-3 py-2">{{ $snapshot['duration_weeks'] }} {{ Str::plural('week', $snapshot['duration_weeks']) }}</span>
                        @endif
                    </div>
                </div>

                <div class="mt-10 rounded-2xl border border-indigo-400/20 bg-indigo-400/10 p-5">
                    <p class="font-semibold text-indigo-100">Save your own copy</p>
                    <p class="mt-2 text-sm leading-6 text-slate-300">Open this plan in Gym Atlas to review every exercise, then choose whether to add it to your workouts. The sender's plan will not be changed.</p>
                    <a
                        href="{{ $appUri }}"
                        data-open-member-app
                        data-android-store="{{ $androidStoreUrl }}"
                        data-ios-store="{{ $iosStoreUrl }}"
                        class="mt-5 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 px-5 py-3 font-bold text-white shadow-lg shadow-indigo-950/30 transition hover:bg-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    >
                        <i class="ti ti-device-mobile" aria-hidden="true"></i>
                        Open in Gym Atlas
                    </a>
                    <p class="mt-3 text-center text-xs leading-5 text-slate-400">If the app is not installed, we’ll take you to the correct store when its store link is available.</p>
                </div>

                <div class="mt-auto pt-8 text-xs text-slate-500">Link expires {{ $share->expires_at?->format('j M Y') ?? 'when the sender revokes it' }}.</div>
            </section>

            <section class="bg-white p-7 text-slate-900 sm:p-10">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-indigo-600">Plan preview</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight">What you’ll be training</h2>

                <div class="mt-7 space-y-4">
                    @forelse($days as $day)
                        <article class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Day {{ $day['day_number'] ?? $loop->iteration }}</p>
                                    <h3 class="mt-1 text-lg font-bold">{{ $day['label'] ?? $day['focus'] ?? 'Training day' }}</h3>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ count($day['exercises'] ?? []) }} exercises</span>
                            </div>
                            <ul class="mt-4 divide-y divide-slate-100">
                                @foreach(($day['exercises'] ?? []) as $exercise)
                                    <li class="flex items-center justify-between gap-4 py-3 text-sm">
                                        <span class="font-semibold text-slate-800">{{ $exercise['exercise_name'] ?? 'Exercise' }}</span>
                                        <span class="shrink-0 text-slate-500">
                                            {{ $exercise['sets'] ?? '—' }} sets
                                            @if(!empty($exercise['reps']))
                                                · {{ $exercise['reps'] }} reps
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @empty
                        <div class="rounded-2xl bg-slate-100 p-5 text-sm text-slate-600">The full plan will be available when you open it in Gym Atlas.</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>

    <script>
        document.querySelector('[data-open-member-app]')?.addEventListener('click', function (event) {
            const userAgent = navigator.userAgent || '';
            const fallback = /android/i.test(userAgent)
                ? this.dataset.androidStore
                : /iphone|ipad|ipod/i.test(userAgent)
                    ? this.dataset.iosStore
                    : '';

            if (!fallback) return;
            event.preventDefault();
            const appUri = this.href;
            const startedAt = Date.now();
            window.location.href = appUri;
            window.setTimeout(function () {
                if (!document.hidden && Date.now() - startedAt < 2200) {
                    window.location.href = fallback;
                }
            }, 1200);
        });
    </script>
</x-public.layouts.enrollment>
