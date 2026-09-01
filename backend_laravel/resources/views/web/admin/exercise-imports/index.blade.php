@extends('layouts.panel')

@section('content')
    @section('page_actions')
        <x-action-button as="a" href="{{ route('web.admin.exercises.index') }}" variant="secondary">Back to Exercise Book</x-action-button>
    @endsection

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="max-w-3xl">
                <div class="panel-toolbar-chip">Catalog Governance</div>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Exercise Import Review</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Review importer provenance, validation counts, taxonomy issues, translations, and publication history before staged exercises become available in Member and Trainer workflows.</p>
            </div>
        </section>

        <x-premium-card class="overflow-hidden">
            <div class="overflow-x-auto">
                <table class="panel-table min-w-[1120px]">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Source</th>
                            <th>Validation</th>
                            <th>Changes</th>
                            <th>Translations / Media</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            @php($counts = $batch->counts ?? [])
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-950 dark:text-white">Batch #{{ $batch->id }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($batch->completed_at ?? $batch->started_at)->format('d M Y, H:i') }}</div>
                                    <div class="mt-2"><x-status-badge :label="str($batch->status)->replace('_', ' ')->title()" :tone="$batch->status === 'completed' ? 'success' : ($batch->status === 'failed' ? 'danger' : 'warning')" /></div>
                                </td>
                                <td>
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ $batch->source_key }}</div>
                                    <div class="mt-1 max-w-[250px] break-all text-xs text-slate-500 dark:text-slate-400">{{ $batch->source_commit ?: 'No pinned commit' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $batch->license_code }} · {{ $batch->sources_count }} currently linked rows</div>
                                </td>
                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                    <div>{{ number_format((int) ($counts['accepted_rows'] ?? 0)) }} accepted</div>
                                    <div>{{ number_format((int) ($counts['rejected_rows'] ?? 0)) }} rejected</div>
                                    <div>{{ count($counts['duplicate_normalized_names'] ?? []) }} duplicate-name findings</div>
                                </td>
                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                    <div>{{ number_format((int) ($counts['new_exercises'] ?? 0)) }} new</div>
                                    <div>{{ number_format((int) ($counts['updated_exercises'] ?? 0)) }} updated</div>
                                    <div>{{ number_format((int) ($counts['unchanged_exercises'] ?? 0)) }} unchanged</div>
                                </td>
                                <td class="text-sm text-slate-600 dark:text-slate-300">
                                    <div>{{ number_format((int) ($counts['translations_upserted'] ?? 0)) }} translations</div>
                                    <div>{{ number_format((int) ($counts['media_references_ignored'] ?? 0)) }} media references ignored</div>
                                    @if (isset($counts['publication']))
                                        <div class="mt-1 text-emerald-700 dark:text-emerald-300">{{ number_format((int) ($counts['publication']['published_now'] ?? 0)) }} published in last review</div>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-action-button as="a" href="{{ route('web.admin.exercise-imports.show', $batch) }}" variant="secondary">Review Batch</x-action-button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No applied import batches exist yet. Dry runs intentionally do not create audit rows.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-premium-card>

        {{ $batches->links() }}
    </div>
@endsection
