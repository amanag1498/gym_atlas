@extends('layouts.panel')

@section('content')
    @php
        $tone = match ($trainerProfile->verification_status) {
            'verified' => 'success',
            'rejected', 'suspended' => 'danger',
            'pending' => 'warning',
            default => 'neutral',
        };
        $displayValue = function (mixed $value): string {
            if (is_array($value)) {
                return collect($value)->map(function ($item): string {
                    if (is_array($item)) {
                        return collect($item)->filter(fn ($value) => filled($value))->implode(' · ');
                    }

                    return (string) $item;
                })->filter()->implode(', ');
            }

            return filled($value) ? (string) $value : 'Not supplied';
        };
        $allowedRestrictiveDecisions = !$trainerProfile->verification_submitted_at ? [] : match ($trainerProfile->verification_status) {
            'pending' => ['rejected' => 'Reject application'],
            'verified' => ['suspended' => 'Suspend verified access'],
            'rejected', 'suspended' => ['pending' => 'Return to pending review'],
            default => [],
        };
    @endphp

    <div class="space-y-6">
        <x-premium-card class="p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-brand-50 text-xl font-bold text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                        @if ($trainerProfile->profile_photo_url)
                            <img src="{{ $trainerProfile->profile_photo_url }}" alt="{{ $trainerProfile->user?->name }}" class="h-full w-full object-cover">
                        @else
                            {{ str($trainerProfile->user?->name ?? 'T')->substr(0, 1)->upper() }}
                        @endif
                    </div>
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-slate-950 dark:text-white">{{ $trainerProfile->user?->name ?? 'Unknown trainer' }}</h2>
                            <x-status-badge :label="str($trainerProfile->verification_status)->title()" :tone="$tone" />
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $trainerProfile->user?->email }} @if ($trainerProfile->user?->phone) · {{ $trainerProfile->user->phone }} @endif</p>
                        <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-brand-600 dark:text-brand-300">{{ $trainerProfile->gym?->name ? 'Gym assigned · '.$trainerProfile->gym->name : 'No gym assignment' }} · personal coaching verification</p>
                    </div>
                </div>
                <x-action-button as="a" href="{{ route('web.admin.trainer-verifications.index') }}" variant="secondary">Back to queue</x-action-button>
            </div>
        </x-premium-card>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,0.65fr)]">
            <div class="space-y-6">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Profile and submitted evidence</h3>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div><div class="panel-label">Primary specialization</div><p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $displayValue($trainerProfile->specialization) }}</p></div>
                        <div><div class="panel-label">Experience</div><p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $trainerProfile->experience_years !== null ? $trainerProfile->experience_years.' years' : 'Not supplied' }}</p></div>
                        <div><div class="panel-label">Specializations</div><p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $displayValue($trainerProfile->specializations) }}</p></div>
                        <div><div class="panel-label">Languages</div><p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ $displayValue($trainerProfile->languages) }}</p></div>
                        <div class="sm:col-span-2"><div class="panel-label">Bio</div><p class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $displayValue($trainerProfile->bio) }}</p></div>
                        <div class="sm:col-span-2">
                            <div class="panel-label">Certifications</div>
                            <div class="mt-2 space-y-2">
                                @forelse (($trainerProfile->certifications ?? []) as $certificate)
                                    @php
                                        $certificateName = is_array($certificate) ? ($certificate['name'] ?? $certificate['file_name'] ?? 'Certification evidence') : $certificate;
                                        $certificateIssuer = is_array($certificate) ? ($certificate['issuer'] ?? null) : null;
                                        $certificateUrl = is_array($certificate) ? ($certificate['file_url'] ?? null) : null;
                                    @endphp
                                    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                        <div><div class="text-sm font-medium text-slate-900 dark:text-white">{{ $certificateName }}</div>@if ($certificateIssuer)<div class="text-xs text-slate-500">{{ $certificateIssuer }}</div>@endif</div>
                                        @if ($certificateUrl)<a href="{{ $certificateUrl }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-300">Open evidence</a>@endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No certification evidence supplied.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Verification history</h3>
                    <div class="mt-5 space-y-4">
                        @forelse ($auditLogs as $log)
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ str($log->event)->afterLast('verification_')->replace('_', ' ')->title() }}</div>
                                    <div class="text-xs text-slate-500">{{ ($log->occurred_at ?? $log->created_at)?->format('d M Y, h:i A') }}</div>
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Reviewed by {{ $log->actor?->name ?? 'Platform admin' }}</p>
                                @if (data_get($log->new_values, 'verification_rejection_reason'))
                                    <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ data_get($log->new_values, 'verification_rejection_reason') }}</p>
                                @endif
                            </div>
                        @empty
                            <x-empty-state title="No review history" message="This submission has not yet been reviewed." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>

            <div class="space-y-6">
                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Review decision</h3>
                    <p class="panel-section-copy">Approval enables personal coaching invitations and does not change this trainer's gym assignment. Rejection and suspension require a reason.</p>

                    @if ($trainerProfile->verification_status !== 'verified' && $trainerProfile->verification_submitted_at)
                    <form method="POST" action="{{ route('web.admin.trainer-verifications.update', $trainerProfile) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="verification_status" value="verified">
                        <div>
                            <label class="panel-label" for="approval_notes">Internal review notes</label>
                            <textarea id="approval_notes" name="notes" rows="3" class="panel-input" placeholder="Evidence checked, certification notes, or follow-up context"></textarea>
                        </div>
                        <x-action-button type="submit" class="w-full justify-center">Approve trainer verification</x-action-button>
                    </form>
                    @endif

                    @if ($allowedRestrictiveDecisions !== [])
                    <div class="my-6 border-t border-slate-200 dark:border-slate-800"></div>

                    <form method="POST" action="{{ route('web.admin.trainer-verifications.update', $trainerProfile) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')
                        <div>
                            <label class="panel-label" for="decision_status">Restrictive decision</label>
                            <select id="decision_status" name="verification_status" class="panel-select" required>
                                @foreach ($allowedRestrictiveDecisions as $decisionValue => $decisionLabel)
                                    <option value="{{ $decisionValue }}">{{ $decisionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="panel-label" for="reason">Reason</label>
                            <textarea id="reason" name="reason" rows="3" class="panel-input" placeholder="Required for rejection or suspension"></textarea>
                            @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="panel-label" for="review_notes">Internal notes</label>
                            <textarea id="review_notes" name="notes" rows="3" class="panel-input" placeholder="Optional reviewer context"></textarea>
                        </div>
                        <x-action-button type="submit" variant="danger" class="w-full justify-center">Apply decision</x-action-button>
                    </form>
                    @endif
                </x-premium-card>

                <x-premium-card class="p-6">
                    <h3 class="panel-section-title">Current review metadata</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-slate-500">Reviewer</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $trainerProfile->verificationReviewer?->name ?? 'Not reviewed' }}</dd></div>
                        <div><dt class="text-slate-500">Submitted at</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $trainerProfile->verification_submitted_at?->format('d M Y, h:i A') ?? 'Not submitted' }}</dd></div>
                        <div><dt class="text-slate-500">Reviewed at</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $trainerProfile->verification_reviewed_at?->format('d M Y, h:i A') ?? 'Not reviewed' }}</dd></div>
                        <div><dt class="text-slate-500">First verified at</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $trainerProfile->verification_verified_at?->format('d M Y, h:i A') ?? 'Not verified' }}</dd></div>
                        @if ($trainerProfile->verification_rejection_reason)
                            <div><dt class="text-slate-500">Restriction reason</dt><dd class="font-medium text-rose-600 dark:text-rose-300">{{ $trainerProfile->verification_rejection_reason }}</dd></div>
                        @endif
                    </dl>
                </x-premium-card>
            </div>
        </div>
    </div>
@endsection
