@extends('layouts.panel')

@section('content')
    @php
        $scope = request()->query();
        $totalLinks = $links->count();
        $activeLinks = $links->where('is_active', true)->count();
        $totalSubmissions = (int) $links->sum('submissions_count');
    @endphp

    <div class="space-y-6">
        <section class="panel-hero overflow-hidden">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-end">
                <div>
                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">Member enrollment</span>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Enrollment QR codes</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Share a branch link digitally or place its poster at reception. Members scan, create or reuse their Gym Atlas profile, and join the correct branch.</p>
                    <div class="mt-5">
                        <a href="{{ route('web.gym.members.index', $scope) }}" class="panel-btn-secondary"><i class="ti ti-arrow-left" aria-hidden="true"></i>Back to members</a>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3" aria-label="Enrollment summary">
                    <div class="rounded-2xl border border-white/60 bg-white/75 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
                        <div class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $totalLinks }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-[.14em] text-slate-500 dark:text-slate-400">QR codes</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <div class="text-2xl font-semibold text-emerald-700 dark:text-emerald-200">{{ $activeLinks }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-[.14em] text-emerald-700/80 dark:text-emerald-200/80">Active</div>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/10">
                        <div class="text-2xl font-semibold text-sky-700 dark:text-sky-200">{{ $totalSubmissions }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-[.14em] text-sky-700/80 dark:text-sky-200/80">Submissions</div>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="enrollment-codes-heading">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="enrollment-codes-heading" class="panel-section-title">Branch enrollment codes</h2>
                    <p class="panel-section-copy">Use the code for the branch where the member should be enrolled.</p>
                </div>
                <x-status-badge :label="$activeLinks.' of '.$totalLinks.' active'" :tone="$activeLinks === $totalLinks ? 'success' : 'warning'" />
            </div>

            <div class="space-y-5">
                @forelse($links as $link)
                    @php
                        $url = route('public.self-enrollment.show', $link->token);
                        $branchName = $link->branch?->name ?? 'All branches';
                        $qrRouteParams = ['gym' => $gym->id, 'link' => $link->id] + $scope;
                    @endphp

                    <x-premium-card class="overflow-hidden p-0">
                        <article aria-labelledby="enrollment-link-{{ $link->id }}-title">
                            <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4 dark:border-slate-800 dark:bg-slate-900/50 sm:px-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200"><i class="ti ti-building-store" aria-hidden="true"></i></span>
                                            <h3 id="enrollment-link-{{ $link->id }}-title" class="truncate text-lg font-semibold text-slate-950 dark:text-white">{{ $branchName }}</h3>
                                            <x-status-badge :label="$link->is_active ? 'Ready to scan' : 'Paused'" :tone="$link->is_active ? 'success' : 'neutral'" />
                                        </div>
                                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $link->name }} · {{ $link->submissions_count }} {{ Str::plural('enrollment', $link->submissions_count) }}</p>
                                    </div>

                                    @if (! $link->is_active)
                                        <p class="max-w-sm rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium leading-5 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">This QR is paused. Scans cannot start enrollment until you enable it.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[250px_minmax(0,1fr)] lg:items-center">
                                <div class="mx-auto w-full max-w-[250px] lg:mx-0">
                                    <x-admin.branded-qr-preview
                                        class="w-full"
                                        :src="route('web.gym.self-enrollment.qr', $qrRouteParams)"
                                        :alt="'Enrollment QR for '.$link->name"
                                        eyebrow="SCAN TO JOIN"
                                        :caption="'Join '.$branchName.' with Gym Atlas'"
                                        tone="enrollment"
                                        size="lg"
                                    />
                                </div>

                                <div class="min-w-0">
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                                        <div class="flex items-start gap-3">
                                            <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-200"><i class="ti ti-link" aria-hidden="true"></i></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-semibold text-slate-950 dark:text-white">Enrollment link</div>
                                                <p class="mt-1 truncate font-mono text-xs text-slate-500 dark:text-slate-400" title="{{ $url }}">{{ $url }}</p>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                            <a href="{{ route('web.gym.self-enrollment.qr', $qrRouteParams + ['download' => 1]) }}" class="panel-btn-primary justify-center"><i class="ti ti-download" aria-hidden="true"></i>Download poster</a>
                                            <button type="button" class="panel-btn-secondary justify-center" data-copy-enrollment-link data-copy-value="{{ $url }}"><i class="ti ti-copy" aria-hidden="true"></i><span data-copy-label>Copy link</span></button>
                                            <a href="{{ $url }}" target="_blank" rel="noopener" class="panel-btn-secondary justify-center sm:col-span-2 xl:col-span-1"><i class="ti ti-external-link" aria-hidden="true"></i>Preview page</a>
                                        </div>
                                        <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">The poster is print-ready and includes the gym name, branch name, scan instructions, and a high-resolution QR.</p>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <form method="POST" action="{{ route('web.gym.self-enrollment.toggle', ['gym' => $gym->id, 'link' => $link->id] + $scope) }}">
                                            @csrf
                                            <button type="submit" class="panel-btn-secondary w-full justify-center sm:w-auto"><i class="ti {{ $link->is_active ? 'ti-player-pause' : 'ti-player-play' }}" aria-hidden="true"></i>{{ $link->is_active ? 'Pause enrollment' : 'Enable enrollment' }}</button>
                                        </form>

                                        <details class="group sm:text-right">
                                            <summary class="inline-flex cursor-pointer list-none items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:text-white">Link settings<i class="ti ti-chevron-down transition group-open:rotate-180" aria-hidden="true"></i></summary>
                                            <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-left dark:border-rose-500/20 dark:bg-rose-500/10 sm:max-w-md">
                                                <div class="text-sm font-semibold text-rose-900 dark:text-rose-100">Replace this QR code</div>
                                                <p class="mt-1 text-xs leading-5 text-rose-700 dark:text-rose-200/80">Use this only if the link was shared by mistake. Existing posters and copied links will stop working immediately.</p>
                                                <form method="POST" action="{{ route('web.gym.self-enrollment.rotate', ['gym' => $gym->id, 'link' => $link->id] + $scope) }}" class="mt-3" data-confirm-submit data-confirm-title="Replace this QR?" data-confirm-message="The printed and copied old link will stop working immediately." data-confirm-button="Generate new QR">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 dark:border-rose-500/30 dark:bg-slate-950/30 dark:text-rose-200 dark:hover:bg-rose-500/10"><i class="ti ti-refresh" aria-hidden="true"></i>Generate a new QR</button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </x-premium-card>
                @empty
                    <x-premium-card class="p-6"><x-empty-state title="No enrollment QR codes" message="Create a branch first, then return here to generate its enrollment code." /></x-premium-card>
                @endforelse
            </div>
        </section>

        <x-premium-card class="overflow-hidden p-0">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 class="panel-section-title">Recent enrollments</h2><p class="panel-section-copy">The latest submissions from every QR code.</p></div>
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
