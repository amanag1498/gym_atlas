@extends('layouts.panel')

@section('content')
    @php
        $scope = request()->except('link');
        $totalLinks = $links->count();
        $activeLinks = $links->where('is_active', true)->count();
        $totalSubmissions = (int) $links->sum('submissions_count');
        $selectedLink = $links->firstWhere('id', (int) request('link')) ?? $links->first();
        $selectedUrl = $selectedLink ? route('public.self-enrollment.show', $selectedLink->token) : null;
        $selectedBranchName = $selectedLink?->branch?->name ?? 'All branches';
        $selectedQrRouteParams = $selectedLink ? ['gym' => $gym->id, 'link' => $selectedLink->id] + $scope : [];
    @endphp

    <div class="space-y-6">
        <section class="panel-hero overflow-hidden">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('web.gym.members.index', $scope) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white"><i class="ti ti-arrow-left" aria-hidden="true"></i>Members</a>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Enrollment QR codes</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Choose a location, then share its link or download the reception poster.</p>
                </div>
                <div class="flex flex-wrap gap-2" aria-label="Enrollment summary">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ $activeLinks }} active</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><i class="ti ti-user-check" aria-hidden="true"></i>{{ $totalSubmissions }} submissions</span>
                </div>
            </div>
        </section>

        <x-premium-card class="overflow-hidden p-0">
            @if ($selectedLink)
                <div class="grid min-w-0 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <aside class="min-w-0 border-b border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/50 lg:border-b-0 lg:border-r" aria-label="Enrollment links">
                        <div class="flex items-center justify-between px-2 pb-3">
                            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Locations</h2>
                            <span class="text-xs font-medium text-slate-400">{{ $totalLinks }}</span>
                        </div>
                        <nav class="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-1 lg:overflow-visible">
                            @foreach($links as $link)
                                @php
                                    $branchName = $link->branch?->name ?? 'All branches';
                                    $isSelected = $selectedLink->is($link);
                                @endphp
                                <a href="{{ route('web.gym.self-enrollment.index', $scope + ['link' => $link->id]) }}" @class([
                                    'block min-w-[220px] rounded-2xl px-3 py-3 transition lg:min-w-0 lg:w-full',
                                    'bg-white shadow-sm ring-1 ring-slate-200 dark:bg-white/10 dark:ring-white/10' => $isSelected,
                                    'hover:bg-white/70 dark:hover:bg-white/5' => ! $isSelected,
                                ]) @if($isSelected) aria-current="page" @endif>
                                    <span class="flex items-center gap-3">
                                        <span @class(['h-2.5 w-2.5 shrink-0 rounded-full', 'bg-emerald-500' => $link->is_active, 'bg-slate-300 dark:bg-slate-600' => ! $link->is_active])></span>
                                        <span class="min-w-0 flex-1 text-left">
                                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $branchName }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $link->submissions_count }} {{ Str::plural('submission', $link->submissions_count) }}</span>
                                        </span>
                                        <i class="ti ti-chevron-right shrink-0 text-slate-400" aria-hidden="true"></i>
                                    </span>
                                </a>
                            @endforeach
                        </nav>
                    </aside>

                    <article class="min-w-0" aria-labelledby="selected-enrollment-title">
                        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 id="selected-enrollment-title" class="truncate text-lg font-semibold text-slate-950 dark:text-white">{{ $selectedBranchName }}</h2>
                                    <x-status-badge :label="$selectedLink->is_active ? 'Active' : 'Paused'" :tone="$selectedLink->is_active ? 'success' : 'neutral'" />
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $selectedLink->name }}</p>
                            </div>
                            <form method="POST" action="{{ route('web.gym.self-enrollment.toggle', ['gym' => $gym->id, 'link' => $selectedLink->id] + $scope) }}">
                                @csrf
                                <button type="submit" class="panel-btn-secondary w-full justify-center sm:w-auto"><i class="ti {{ $selectedLink->is_active ? 'ti-player-pause' : 'ti-player-play' }}" aria-hidden="true"></i>{{ $selectedLink->is_active ? 'Pause' : 'Enable' }}</button>
                            </form>
                        </div>

                        <div class="grid gap-7 p-5 sm:p-6 xl:grid-cols-[280px_minmax(0,1fr)] xl:items-center">
                            <div class="mx-auto w-full max-w-[280px]">
                                <x-admin.branded-qr-preview
                                    class="w-full"
                                    :src="route('web.gym.self-enrollment.qr', $selectedQrRouteParams)"
                                    :alt="'Enrollment QR for '.$selectedLink->name"
                                    eyebrow="SCAN TO JOIN"
                                    :caption="'Join '.$selectedBranchName.' with Gym Atlas'"
                                    tone="enrollment"
                                    size="lg"
                                />
                            </div>

                            <div class="min-w-0">
                                @if (! $selectedLink->is_active)
                                    <div class="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"><i class="ti ti-alert-circle mt-0.5 shrink-0" aria-hidden="true"></i><span>Scans are paused until this link is enabled.</span></div>
                                @endif

                                <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                                    <div class="text-xs font-semibold uppercase tracking-[.14em] text-slate-400">Share link</div>
                                    <p class="mt-2 truncate font-mono text-xs text-slate-600 dark:text-slate-300" title="{{ $selectedUrl }}">{{ $selectedUrl }}</p>
                                </div>

                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                    <a href="{{ route('web.gym.self-enrollment.qr', $selectedQrRouteParams + ['download' => 1]) }}" class="panel-btn-primary justify-center sm:col-span-2"><i class="ti ti-download" aria-hidden="true"></i>Download poster</a>
                                    <button type="button" class="panel-btn-secondary justify-center" data-copy-enrollment-link data-copy-value="{{ $selectedUrl }}"><i class="ti ti-copy" aria-hidden="true"></i><span data-copy-label>Copy link</span></button>
                                    <a href="{{ $selectedUrl }}" target="_blank" rel="noopener" class="panel-btn-secondary justify-center"><i class="ti ti-external-link" aria-hidden="true"></i>Preview page</a>
                                </div>

                                <details class="group mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                                    <summary class="inline-flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:text-white">Link settings<i class="ti ti-chevron-down transition group-open:rotate-180" aria-hidden="true"></i></summary>
                                    <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
                                        <div class="text-sm font-semibold text-rose-900 dark:text-rose-100">Replace this QR code</div>
                                        <p class="mt-1 text-xs leading-5 text-rose-700 dark:text-rose-200/80">Printed posters and copied links will stop working immediately.</p>
                                        <form method="POST" action="{{ route('web.gym.self-enrollment.rotate', ['gym' => $gym->id, 'link' => $selectedLink->id] + $scope) }}" class="mt-3" data-confirm-submit data-confirm-title="Replace this QR?" data-confirm-message="The printed and copied old link will stop working immediately." data-confirm-button="Generate new QR">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 dark:border-rose-500/30 dark:bg-slate-950/30 dark:text-rose-200 dark:hover:bg-rose-500/10"><i class="ti ti-refresh" aria-hidden="true"></i>Generate new QR</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </article>
                </div>
            @else
                <div class="p-6"><x-empty-state title="No enrollment QR codes" message="Create a branch first, then return here to generate its enrollment code." /></div>
            @endif
        </x-premium-card>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <h2 class="panel-section-title">Recent enrollments</h2>
                    <x-status-badge :label="$recentSubmissions->count().' recent'" tone="info" />
                </div>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 md:hidden">
                @forelse($recentSubmissions as $submission)
                    <article class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><div class="truncate font-semibold text-slate-950 dark:text-white">{{ $submission->user?->name ?? $submission->submitted_name }}</div><div class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $submission->submitted_email }}</div></div>
                            <x-status-badge :label="str($submission->outcome)->replace('_',' ')->title()" :tone="$submission->outcome === 'enrolled' ? 'success' : ($submission->outcome === 'inactive_member' ? 'warning' : 'info')" />
                        </div>
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-xs font-medium uppercase tracking-[.12em] text-slate-400">Branch</dt><dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $submission->branch?->name ?? 'General link' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase tracking-[.12em] text-slate-400">Source</dt><dd class="mt-1 text-slate-700 dark:text-slate-200">{{ str($submission->source)->replace('_', ' ')->title() }}</dd></div>
                        </dl>
                        <time class="mt-4 block text-xs text-slate-500 dark:text-slate-400" datetime="{{ $submission->created_at?->toIso8601String() }}">{{ $submission->created_at?->format('d M Y, h:i A') }}</time>
                    </article>
                @empty
                    <div class="p-5"><x-empty-state title="No QR enrollments yet" message="Share a link or place a poster at reception. The first submission will appear here." /></div>
                @endforelse
            </div>

            <div class="hidden md:block">
                <x-table-wrapper>
                    <table class="panel-table">
                        <thead><tr><th>Member</th><th>Branch</th><th>Source</th><th>Outcome</th><th>Submitted</th></tr></thead>
                        <tbody>
                            @forelse($recentSubmissions as $submission)
                                <tr>
                                    <td><div class="font-semibold">{{ $submission->user?->name ?? $submission->submitted_name }}</div><div class="text-xs text-slate-500">{{ $submission->submitted_email }}</div></td>
                                    <td>{{ $submission->branch?->name ?? 'General link' }}</td>
                                    <td>{{ str($submission->source)->replace('_', ' ')->title() }}</td>
                                    <td><x-status-badge :label="str($submission->outcome)->replace('_',' ')->title()" :tone="$submission->outcome === 'enrolled' ? 'success' : ($submission->outcome === 'inactive_member' ? 'warning' : 'info')" /></td>
                                    <td><time datetime="{{ $submission->created_at?->toIso8601String() }}">{{ $submission->created_at?->format('d M Y, h:i A') }}</time></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty-state title="No QR enrollments yet" message="Share a link or place a poster at reception. The first submission will appear here." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-table-wrapper>
            </div>
        </x-premium-card>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy-enrollment-link]').forEach((button) => {
            button.addEventListener('click', async () => {
                const label = button.querySelector('[data-copy-label]');
                const originalLabel = label.textContent;

                try {
                    await navigator.clipboard.writeText(button.dataset.copyValue);
                    label.textContent = 'Copied';
                    button.setAttribute('aria-live', 'polite');
                } catch (error) {
                    label.textContent = 'Copy failed';
                }

                window.setTimeout(() => {
                    label.textContent = originalLabel;
                    button.removeAttribute('aria-live');
                }, 1800);
            });
        });
    </script>
@endpush
