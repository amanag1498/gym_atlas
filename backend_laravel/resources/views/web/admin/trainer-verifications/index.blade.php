@extends('layouts.panel')

@section('content')
    @php
        $toneFor = fn (string $status) => match ($status) {
            'verified' => 'success',
            'rejected', 'suspended' => 'danger',
            'pending' => 'warning',
            default => 'neutral',
        };
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card label="Pending" :value="max(0, (int) ($counts['pending'] ?? 0) - $notSubmittedCount)" hint="Submitted for review" tone="amber" />
            <x-stat-card label="Not submitted" :value="$notSubmittedCount" hint="Trainer action required" tone="neutral" />
            <x-stat-card label="Verified" :value="(int) ($counts['verified'] ?? 0)" hint="Personal coaching enabled" tone="emerald" />
            <x-stat-card label="Rejected" :value="(int) ($counts['rejected'] ?? 0)" hint="Requirements not met" tone="rose" />
            <x-stat-card label="Suspended" :value="(int) ($counts['suspended'] ?? 0)" hint="Independent access blocked" tone="violet" />
        </div>

        <x-premium-card class="p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(200px,1fr)_auto] md:items-end">
                <x-form-input name="search" label="Search submissions" :value="request('search')" placeholder="Trainer name, email, or specialization" />
                <x-form-select
                    name="status"
                    label="Verification status"
                    :selected="request('status')"
                    :options="[
                        '' => 'All statuses',
                        'pending' => 'Pending',
                        'not_submitted' => 'Not submitted',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                        'suspended' => 'Suspended',
                    ]"
                />
                <div class="flex flex-wrap gap-2">
                    <x-action-button type="submit">Apply</x-action-button>
                    <x-action-button as="a" href="{{ route('web.admin.trainer-verifications.index') }}" variant="secondary">Reset</x-action-button>
                </div>
            </form>
        </x-premium-card>

        <x-table-wrapper class="overflow-hidden p-0">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="panel-section-title">Trainer verification queue</h2>
                <p class="panel-section-copy">Verification controls personal coaching access only. A trainer may remain assigned to a gym and also coach their own verified clients.</p>
            </div>

            @if ($submissions->count())
                <div class="overflow-x-auto">
                    <table class="panel-table min-w-[960px]">
                        <thead>
                            <tr>
                                <th>Trainer</th>
                                <th>Experience</th>
                                <th>Evidence</th>
                                <th>Status</th>
                                <th>Last review</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($submissions as $submission)
                                <tr>
                                    <td>
                                        <div class="font-semibold text-slate-950 dark:text-white">{{ $submission->user?->name ?? 'Unknown trainer' }}</div>
                                        <div class="text-xs text-slate-500">{{ $submission->user?->email }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $submission->gym?->name ? 'Gym: '.$submission->gym->name : 'No gym assignment' }}</div>
                                    </td>
                                    <td>
                                        <div class="text-sm text-slate-700 dark:text-slate-300">{{ $submission->specialization ?: 'Not supplied' }}</div>
                                        <div class="text-xs text-slate-500">{{ $submission->experience_years !== null ? $submission->experience_years.' years' : 'Experience not supplied' }}</div>
                                    </td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        {{ count($submission->certifications ?? []) }} certification record(s)
                                    </td>
                                    <td><x-status-badge :label="$submission->verification_status === 'pending' && !$submission->verification_submitted_at ? 'Not submitted' : str($submission->verification_status)->title()" :tone="$toneFor($submission->verification_status)" /></td>
                                    <td class="text-sm text-slate-600 dark:text-slate-300">
                                        @if ($submission->verification_reviewed_at)
                                            <div>{{ $submission->verification_reviewed_at->format('d M Y, h:i A') }}</div>
                                            <div class="text-xs text-slate-500">{{ $submission->verificationReviewer?->name ?? 'Reviewer unavailable' }}</div>
                                        @else
                                            Never reviewed
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <x-action-button as="a" href="{{ route('web.admin.trainer-verifications.show', $submission) }}">Review</x-action-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5"><x-empty-state title="No verification submissions" message="No trainers match the selected filters." /></div>
            @endif

            @if ($submissions->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $submissions->links() }}</div>
            @endif
        </x-table-wrapper>
    </div>
@endsection
