@extends('layouts.panel')

@section('content')
    @php
        $maxDailyTrend = max(1, (int) $dailyTrend->max('count'));
        $maxHourlyCount = max(1, (int) $hourlyHeatmap->max('count'));
        $scopeQuery = request()->only(['gym', 'branch']);
    @endphp

    <div class="space-y-5">
        <div class="rounded-[28px] border border-slate-200 bg-white px-5 py-5 shadow-[0_28px_70px_-52px_rgba(15,23,42,0.42)] dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">Attendance Ops</span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-medium {{ $duplicateProtectionEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100' }}">
                            {{ $duplicateProtectionEnabled ? 'Duplicate protection on' : 'Duplicate protection off' }}
                        </span>
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Attendance</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
                        Live desk flow for check-ins, attendance history, pattern review, and correction handling.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('web.gym.attendance.index', $scopeQuery + ['today' => 1]) }}" class="panel-btn-secondary">Today</a>
                    @if ($canManageAttendance)
                        <a href="{{ route('web.gym.attendance.manual', $scopeQuery) }}" class="panel-btn-primary">Manual Check-in</a>
                    @endif
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="panel-btn-secondary">Export CSV</a>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card label="Today Check-ins" :value="$todayCount" hint="Current day volume" tone="success" />
            <x-stat-card label="Filtered Logs" :value="$summary['visible_logs']" hint="Visible in current ledger" tone="sky" />
            <x-stat-card label="Unique Members" :value="$summary['unique_members']" hint="Distinct attendees in current filter" tone="violet" />
            <x-stat-card label="Avg Daily" :value="$summary['avg_daily_logs']" hint="Average active day volume" tone="amber" />
            <x-stat-card label="Pending Corrections" :value="$summary['pending_corrections']" hint="Needs approval or rejection" tone="warning" />
            <x-stat-card label="Peak Window" :value="$peakHour['label'] ?? 'No pattern'" :hint="$peakHour ? $peakHour['count'].' logs in the strongest hour' : 'No traffic pattern yet'" tone="info" />
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_360px]">
            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Filter Bar</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Use one clean range or quick presets. Avoid stacking conflicting periods.</p>
                        </div>
                        @if ($selectedMember)
                            <x-status-badge :label="'Member: '.$selectedMember->name" tone="info" />
                        @endif
                    </div>
                </div>
                <form method="GET" action="{{ route('web.gym.attendance.index') }}" class="grid gap-3 px-4 py-4 md:grid-cols-2 xl:grid-cols-6">
                    <input type="hidden" name="gym" value="{{ request('gym', $gym->id) }}">
                    @if (request()->filled('branch'))
                        <input type="hidden" name="branch" value="{{ request('branch') }}">
                    @endif
                    <x-form-select name="branch_id" label="Branch" :selected="request('branch_id')" :options="['' => 'All branches'] + $branches->pluck('name', 'id')->all()" />
                    <x-form-select name="member_id" label="Member" :selected="request('member_id')" :options="['' => 'All members'] + $members->pluck('name', 'id')->all()" />
                    <x-form-select name="check_in_method" label="Method" :selected="request('check_in_method')" :options="['' => 'All methods', 'biometric' => 'Biometric', 'manual' => 'Manual']" />
                    <x-form-input name="start_date" label="Start Date" type="date" :value="request('start_date')" />
                    <x-form-input name="end_date" label="End Date" type="date" :value="request('end_date')" />
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <input type="checkbox" class="mr-2" name="today" value="1" @checked(request()->boolean('today'))>
                            Today
                        </label>
                        <label class="flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <input type="checkbox" class="mr-2" name="this_week" value="1" @checked(request()->boolean('this_week'))>
                            Week
                        </label>
                        <label class="flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <input type="checkbox" class="mr-2" name="this_month" value="1" @checked(request()->boolean('this_month'))>
                            Month
                        </label>
                    </div>
                    <div class="xl:col-span-6 flex flex-wrap gap-2">
                        <button type="submit" class="panel-btn-primary">Apply Filters</button>
                        <a href="{{ route('web.gym.attendance.index', $scopeQuery) }}" class="panel-btn-secondary">Reset</a>
                    </div>
                </form>
            </x-premium-card>

            <div class="grid gap-5">
                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Method Split</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Operational mix in the active filter.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($methodBreakdown as $row)
                            @php
                                $total = max(1, $summary['visible_logs']);
                                $width = min(100, round((((int) $row->total_logs) / $total) * 100));
                            @endphp
                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ strtoupper((string) $row->check_in_method) }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $row->total_logs }} logs</div>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-2.5 rounded-full {{ $row->check_in_method === 'biometric' ? 'bg-sky-500' : 'bg-amber-500' }}" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @empty
                            <x-empty-state title="No attendance methods yet" message="Method split appears after the first check-in." />
                        @endforelse
                    </div>
                </x-premium-card>

                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Today Feed</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Latest arrivals in current scope.</p>
                        </div>
                        <x-status-badge :label="$todayCount > 0 ? 'Live' : 'Quiet'" :tone="$todayCount > 0 ? 'success' : 'neutral'" />
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($todayLogs as $log)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-slate-900 dark:text-slate-100">{{ $log->member?->name ?? 'Member' }}</div>
                                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->branch?->name ?? 'Branch not set' }} • {{ optional($log->checked_in_at)->format('d M, h:i A') }}</div>
                                    </div>
                                    <x-status-badge :label="strtoupper((string) $log->check_in_method)" :tone="$log->check_in_method === 'biometric' ? 'info' : 'warning'" />
                                </div>
                            </div>
                        @empty
                            <x-empty-state title="No check-ins yet today" message="New arrivals will appear here." />
                        @endforelse
                    </div>
                </x-premium-card>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.12fr)_minmax(340px,0.88fr)]">
            <x-table-wrapper class="overflow-hidden p-0">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Attendance Ledger</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                @if ($selectedMember)
                                    Full ledger for {{ $selectedMember->name }}.
                                @else
                                    Chronological manual and biometric entries across the current scope.
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                @if ($logs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="panel-table min-w-[1220px]">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Time</th>
                                    <th>Branch</th>
                                    <th>Method</th>
                                    <th>Recorded By</th>
                                    <th>Context</th>
                                    <th class="w-[10rem]">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td>
                                            <div class="font-semibold text-slate-950 dark:text-white">{{ $log->member?->name ?? 'Member' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->member?->email ?? 'No email' }}</div>
                                        </td>
                                        <td>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ optional($log->checked_in_at)->format('d M Y') }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ optional($log->checked_in_at)->format('h:i A') }}</div>
                                        </td>
                                        <td>{{ $log->branch?->name ?? 'N/A' }}</td>
                                        <td><x-status-badge :label="strtoupper((string) $log->check_in_method)" :tone="$log->check_in_method === 'biometric' ? 'info' : 'warning'" /></td>
                                        <td>{{ $log->checkedInByUser?->name ?? 'System' }}</td>
                                        <td class="text-sm text-slate-600 dark:text-slate-300">
                                            <div>{{ $log->source_device ?: 'No source device' }}</div>
                                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->notes ?: 'No notes' }}</div>
                                        </td>
                                        <td>
                                            @if ($canManageAttendance)
                                                <button
                                                    type="button"
                                                    class="panel-btn-secondary !rounded-xl !px-3 !py-2 !text-xs"
                                                    data-attendance-correction-trigger
                                                    data-log-id="{{ $log->id }}"
                                                    data-member-id="{{ $log->member_id }}"
                                                    data-branch-id="{{ $log->branch_id }}"
                                                    data-checkin-at="{{ optional($log->checked_in_at)->format('Y-m-d\TH:i') }}"
                                                    data-member-label="{{ $log->member?->name ?? 'Member' }}"
                                                >
                                                    Correct
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-5 py-6">
                        <x-empty-state title="No attendance recorded" message="Start with a manual or biometric check-in to build the ledger." :action-href="$canManageAttendance ? route('web.gym.attendance.manual', $scopeQuery) : null" :action-label="$canManageAttendance ? 'Manual Check-in' : null" />
                    </div>
                @endif

                @if ($logs->hasPages())
                    <div class="border-t border-slate-200/80 px-5 py-4 dark:border-slate-800">
                        {{ $logs->links() }}
                    </div>
                @endif
            </x-table-wrapper>

            <div class="space-y-5">
                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">7-Day Pattern</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Simple volume trend across the last week.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-7 gap-2">
                        @foreach ($dailyTrend as $day)
                            @php
                                $height = max(14, (int) round(($day['count'] / $maxDailyTrend) * 112));
                            @endphp
                            <div class="flex flex-col items-center justify-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-2 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                                <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $day['count'] }}</div>
                                <div class="flex h-28 items-end">
                                    <div class="w-6 rounded-full bg-sky-500" style="height: {{ $height }}px"></div>
                                </div>
                                <div class="text-[11px] font-medium text-slate-700 dark:text-slate-200">{{ $day['label'] }}</div>
                                <div class="text-[10px] text-slate-400">{{ $day['date'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-premium-card>

                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Peak Hours</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">When members actually arrive based on current data.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($hourlyHeatmap as $slot)
                            @php
                                $width = min(100, (int) round(($slot['count'] / $maxHourlyCount) * 100));
                            @endphp
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $slot['label'] }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">{{ $slot['count'] }} logs</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-2 rounded-full bg-violet-500" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @empty
                            <x-empty-state title="No hour pattern yet" message="Peak-hour analysis appears after more check-ins are recorded." />
                        @endforelse
                    </div>
                </x-premium-card>

                @if ($canManageAttendance)
                    <x-premium-card class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Biometric Desk</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Fast attendance entry using the member biometric identifier.</p>
                            </div>
                            <x-status-badge label="Biometric" tone="info" />
                        </div>
                        <form method="POST" action="{{ route('web.gym.attendance.biometric-scan', $scopeQuery) }}" class="mt-4 grid gap-3">
                            @csrf
                            <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                            <x-form-select name="branch_id" label="Branch" :selected="old('branch_id', request('branch_id'))" :options="['' => 'Select branch'] + $branches->pluck('name', 'id')->all()" />
                            <div>
                                <label class="panel-label" for="biometric_identifier">Biometric Identifier</label>
                                <input id="biometric_identifier" name="biometric_identifier" class="panel-input" value="{{ old('biometric_identifier') }}" placeholder="Scan or enter the biometric member identifier" required>
                            </div>
                            <x-form-input name="source_device" label="Source Device" :value="old('source_device', 'web-biometric-desk')" />
                            <div>
                                <label class="panel-label" for="biometric_notes">Notes</label>
                                <textarea id="biometric_notes" name="notes" class="panel-textarea" rows="3" placeholder="Optional biometric desk notes">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit" class="panel-btn-primary w-full justify-center">Record Biometric Check-in</button>
                        </form>
                    </x-premium-card>
                @endif
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            @if ($canManageAttendance)
                <x-premium-card class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Attendance Correction</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Request a backfill or time correction without directly mutating history from the table.</p>
                        </div>
                        <x-status-badge label="Correction Queue" tone="warning" />
                    </div>
                    <form method="POST" action="{{ route('web.gym.attendance.corrections.store', $scopeQuery) }}" class="mt-4 grid gap-3 md:grid-cols-2" id="attendance-correction-form">
                        @csrf
                        <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                        <input type="hidden" name="attendance_log_id" id="attendance_correction_log_id" value="{{ old('attendance_log_id') }}">
                        <x-form-select name="branch_id" label="Branch" :selected="old('branch_id', request('branch_id'))" :options="$branches->pluck('name', 'id')->all()" required />
                        <x-form-select name="member_id" label="Member" :selected="old('member_id', request('member_id'))" :options="$members->pluck('name', 'id')->all()" required />
                        <x-form-input type="datetime-local" name="requested_check_in_at" label="Requested Check-in Time" :value="old('requested_check_in_at')" required />
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/70">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Linked log</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100" id="attendance_correction_log_label">No existing log linked</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Use the “Correct” action from the ledger to prefill an existing entry.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="panel-label" for="attendance_correction_reason">Reason</label>
                            <textarea id="attendance_correction_reason" name="reason" class="panel-textarea" rows="4" placeholder="Explain why this correction is needed" required>{{ old('reason') }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="panel-btn-primary">Submit Correction Request</button>
                        </div>
                    </form>
                </x-premium-card>
            @endif

            <x-premium-card class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Correction Queue</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Most recent correction requests in this gym scope.</p>
                    </div>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($correctionRequests as $correction)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $correction->member?->name ?? 'Member' }} • {{ $correction->branch?->name ?? 'Branch' }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Requested {{ optional($correction->requested_check_in_at)->format('d M Y h:i A') }} by {{ $correction->requestedByUser?->name ?? 'Unknown' }}
                                    </div>
                                    <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $correction->reason }}</div>
                                </div>
                                <x-status-badge :label="ucfirst((string) $correction->status)" :tone="match((string) $correction->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }" />
                            </div>

                            @if ($canManageAttendance && $correction->status === 'pending')
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('web.gym.attendance.corrections.approve', $scopeQuery + ['correction' => $correction->id]) }}">
                                        @csrf
                                        <button type="submit" class="panel-btn-primary !px-3 !py-2 !text-xs">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('web.gym.attendance.corrections.reject', $scopeQuery + ['correction' => $correction->id]) }}">
                                        @csrf
                                        <button type="submit" class="panel-btn-danger !px-3 !py-2 !text-xs">Reject</button>
                                    </form>
                                </div>
                            @elseif ($correction->reviewedByUser)
                                <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                    Reviewed by {{ $correction->reviewedByUser->name }} on {{ optional($correction->reviewed_at)->format('d M Y h:i A') }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <x-empty-state title="No correction requests yet" message="Backfills and time-fix requests will appear here." />
                    @endforelse
                </div>
            </x-premium-card>
        </div>
    </div>

    @if ($canManageAttendance)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const logIdInput = document.getElementById('attendance_correction_log_id');
                const logLabel = document.getElementById('attendance_correction_log_label');
                const form = document.getElementById('attendance-correction-form');

                document.querySelectorAll('[data-attendance-correction-trigger]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const logId = button.getAttribute('data-log-id') || '';
                        const memberId = button.getAttribute('data-member-id') || '';
                        const branchId = button.getAttribute('data-branch-id') || '';
                        const checkedInAt = button.getAttribute('data-checkin-at') || '';
                        const memberLabel = button.getAttribute('data-member-label') || 'Member';

                        if (logIdInput) {
                            logIdInput.value = logId;
                        }

                        const memberField = form?.querySelector('[name="member_id"]');
                        const branchField = form?.querySelector('[name="branch_id"]');
                        const timeField = form?.querySelector('[name="requested_check_in_at"]');

                        if (memberField) {
                            memberField.value = memberId;
                        }

                        if (branchField) {
                            branchField.value = branchId;
                        }

                        if (timeField) {
                            timeField.value = checkedInAt;
                        }

                        if (logLabel) {
                            logLabel.textContent = `Linked to log #${logId} for ${memberLabel}`;
                        }

                        form?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                });
            });
        </script>
    @endif
@endsection
