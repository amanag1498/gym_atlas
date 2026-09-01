@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.exercise-imports.index') }}" variant="secondary">All Import Batches</x-action-button>
        <x-action-button as="a" href="{{ route('web.admin.exercises.index', ['source_key' => $batch->source_key]) }}" variant="secondary">View Source Exercises</x-action-button>
    @endsection

    @php($counts = $batch->counts ?? [])
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <div class="panel-toolbar-chip">Import Batch #{{ $batch->id }}</div>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $batch->source_key }}</h2>
                    <p class="mt-2 break-all text-sm leading-6 text-slate-500 dark:text-slate-400">Commit {{ $batch->source_commit ?: 'not supplied' }} · {{ $batch->license_code }} · SHA-256 {{ $batch->source_checksum }}</p>
                </div>
                <x-status-badge :label="str($batch->status)->replace('_', ' ')->title()" :tone="$batch->status === 'completed' ? 'success' : ($batch->status === 'failed' ? 'danger' : 'warning')" />
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Linked Rows" :value="$summary['source_records']" hint="Current source records" tone="sky" />
            <x-stat-card label="Eligible" :value="$summary['eligible']" hint="Safe for staged publish" tone="emerald" />
            <x-stat-card label="Published" :value="$summary['published']" hint="Active and approved" tone="violet" />
            <x-stat-card label="Blocked" :value="$summary['blocked']" hint="Needs individual review" tone="amber" />
            <x-stat-card label="Locales" :value="$summary['translation_locales']" hint="Imported translation coverage" tone="sky" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Import Audit</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'Source rows' => $counts['source_rows'] ?? 0,
                        'Accepted' => $counts['accepted_rows'] ?? 0,
                        'Rejected' => $counts['rejected_rows'] ?? 0,
                        'New' => $counts['new_exercises'] ?? 0,
                        'Updated' => $counts['updated_exercises'] ?? 0,
                        'Unchanged' => $counts['unchanged_exercises'] ?? 0,
                        'Name matched' => $counts['matched_existing_by_name'] ?? 0,
                        'Translations' => $counts['translations_upserted'] ?? 0,
                        'Media ignored' => $counts['media_references_ignored'] ?? 0,
                    ] as $label => $value)
                        <div class="panel-card-muted px-4 py-3">
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</div>
                            <div class="mt-1 font-semibold text-slate-950 dark:text-white">{{ number_format((int) $value) }}</div>
                        </div>
                    @endforeach
                </div>
            </x-premium-card>

            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Controlled Publication</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Only deterministic, unambiguous rows with valid taxonomy, tracking semantics, and meaningful English instructions will be activated. Non-English translations remain review-gated.</p>
                <form method="POST" action="{{ route('web.admin.exercise-imports.publish', $batch) }}" class="mt-5 space-y-4">
                    @csrf
                    <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="confirm" value="1" class="mt-1 rounded border-slate-300" required>
                        <span>I reviewed this batch and understand that {{ number_format($summary['eligible']) }} eligible exercises will become selectable.</span>
                    </label>
                    <x-action-button type="submit" :disabled="$batch->status !== 'completed' || $summary['eligible'] === 0">Publish Eligible Rows</x-action-button>
                </form>
                @if (isset($counts['publication']))
                    <div class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        Last publication: {{ number_format((int) ($counts['publication']['published_now'] ?? 0)) }} rows at {{ $counts['publication']['reviewed_at'] ?? 'unknown time' }}.
                    </div>
                @endif
            </x-premium-card>
        </div>

        @if ($summary['blocker_counts'] !== [])
            <x-premium-card class="p-5">
                <h3 class="panel-section-title">Review Blockers</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($summary['blocker_counts'] as $issue => $count)
                        <x-status-badge :label="$issue.' · '.$count" tone="warning" />
                    @endforeach
                </div>
            </x-premium-card>
        @endif

        <x-premium-card class="overflow-hidden">
            <div class="border-b border-slate-200/80 px-5 py-5 dark:border-slate-800">
                <h3 class="panel-section-title">Source Records</h3>
                <p class="panel-section-copy">Rows are linked by source identity. Blocked rows must be corrected and approved individually from the Exercise Book.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="panel-table min-w-[1180px]">
                    <thead><tr><th>Source ID</th><th>Exercise</th><th>Taxonomy</th><th>Instructions</th><th>Review Result</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @forelse ($sources as $source)
                            @php($assessment = $assessments[$source->id] ?? ['state' => 'missing', 'issues' => ['Assessment unavailable']])
                            <tr>
                                <td>
                                    <div class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ $source->source_external_id }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $source->license_code }}</div>
                                </td>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">{{ $source->exercise?->name ?? 'Missing exercise' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">Exercise #{{ $source->exercise_id }}</div>
                                </td>
                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                    <div>{{ str($source->exercise?->body_part ?? 'unmapped')->replace('_', ' ')->title() }}</div>
                                    <div>{{ $source->exercise?->target_muscle ?: 'No target' }} · {{ $source->exercise?->equipment ?: 'No equipment' }}</div>
                                    <div>{{ str($source->exercise?->default_tracking_mode ?? 'unknown')->title() }}</div>
                                </td>
                                <td class="max-w-[320px] text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($source->exercise?->instructions, 130) ?: 'Missing' }}</td>
                                <td>
                                    <x-status-badge :label="str($assessment['state'])->title()" :tone="$assessment['state'] === 'published' ? 'success' : ($assessment['state'] === 'eligible' ? 'neutral' : 'warning')" />
                                    @foreach ($assessment['issues'] as $issue)
                                        <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $issue }}</div>
                                    @endforeach
                                </td>
                                <td class="text-right">
                                    @if ($source->exercise)
                                        <x-action-button as="a" href="{{ route('web.admin.exercises.edit', $source->exercise) }}" variant="secondary">Review Exercise</x-action-button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No source records currently point to this historical batch. Its immutable count report remains above.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-premium-card>

        {{ $sources->links() }}
    </div>
@endsection
