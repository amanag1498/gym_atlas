@extends('layouts.panel')

@php
    $panelFullWidth = true;
@endphp

@section('content')
    <div class="w-full min-w-0 space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Trial request</p>
                <h2 class="mt-1 break-words text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $trial->name }}</h2>
                <div class="mt-2 flex flex-wrap gap-2"><x-status-badge :label="$trial->request_type === 'contact' ? 'Direct Enquiry' : 'Trial Request'" /><x-status-badge :label="ucfirst($trial->status)" /></div>
            </div>
            <div class="flex flex-wrap gap-2">
                    @if ($trial->phone)
                        <x-action-button as="a" href="tel:{{ $trial->phone }}">Call lead</x-action-button>
                    @endif
                    @if ($trial->email)
                        <x-action-button as="a" variant="secondary" href="mailto:{{ $trial->email }}">Email</x-action-button>
                    @endif
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.index', request()->only(['gym', 'branch'])) }}">Back to Trials</x-action-button>
            </div>
        </header>

        <div class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <x-premium-card class="min-w-0 p-5 sm:p-6">
                <h3 class="panel-section-title">Lead profile</h3>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Lead type</p>
                        <p class="mt-2 text-sm text-slate-950 dark:text-white">{{ $trial->request_type === 'contact' ? 'Direct enquiry' : 'Trial request' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Phone</p>
                        <p class="mt-2 break-all text-sm text-slate-950 dark:text-white">{{ $trial->phone ?: 'Not provided' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Email</p>
                        <p class="mt-2 break-all text-sm text-slate-950 dark:text-white">{{ $trial->email ?: 'Not provided' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Branch</p>
                        <p class="mt-2 break-words text-sm text-slate-950 dark:text-white">{{ $trial->branch?->name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Assigned trainer</p>
                        <p class="mt-2 break-words text-sm text-slate-950 dark:text-white">{{ $trial->assignedTrainer?->name ?? 'Not assigned' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred slot</p>
                        <p class="mt-2 text-sm text-slate-950 dark:text-white">{{ optional($trial->preferred_date)->format('d M Y') ?: 'Not set' }}{{ $trial->preferred_time ? ' · '.substr((string) $trial->preferred_time, 0, 5) : '' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Linked member</p>
                        <p class="mt-2 break-words text-sm text-slate-950 dark:text-white">{{ $trial->member?->name ?? 'Not linked yet' }}</p>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="min-w-0 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="panel-section-title">Lead workflow</h3>
                    @unless ($canManage)<x-status-badge label="View only" tone="warning" />@endunless
                </div>
                <div class="mt-6 grid gap-6">
                    @if ($canManage)
                        <form method="POST" action="{{ route('web.gym.trial-requests.assign-trainer', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}" class="grid gap-4">
                            @csrf
                            <div>
                                <label class="panel-label" for="assigned_trainer_id">Follow-up owner</label>
                                <select id="assigned_trainer_id" name="assigned_trainer_id" class="panel-select mt-2">
                                    <option value="">Leave unassigned</option>
                                    @foreach ($trainers as $trainer)
                                        <option value="{{ $trainer->id }}" @selected($trial->assigned_trainer_id === $trainer->id)>{{ $trainer->name }}{{ $trainer->managedTrainerProfile?->branch_id === null ? ' · all branches' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="panel-label" for="assignment_notes">Follow-up notes</label>
                                <textarea id="assignment_notes" name="notes" class="panel-textarea mt-2" rows="4" placeholder="Add context for the trainer">{{ $trial->notes }}</textarea>
                            </div>
                            <x-action-button type="submit">Save trainer assignment</x-action-button>
                        </form>

                        <div class="flex flex-wrap gap-3">
                            @if ($trial->status === 'pending')
                                <form method="POST" action="{{ route('web.gym.trial-requests.accept', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">@csrf<x-action-button type="submit">Accept trial</x-action-button></form>
                                <form method="POST" action="{{ route('web.gym.trial-requests.reject', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">@csrf<x-action-button type="submit" variant="danger">Reject</x-action-button></form>
                            @elseif ($trial->status === 'accepted')
                                <form method="POST" action="{{ route('web.gym.trial-requests.complete', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">@csrf<x-action-button type="submit">Mark trial visited</x-action-button></form>
                            @endif
                        </div>

                    @if ($trial->canConvert())
                        <form method="POST" action="{{ route('web.gym.trial-requests.convert', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}" class="grid gap-4 border-t border-slate-200 pt-6 dark:border-white/10 md:grid-cols-2">
                        @csrf
                        <div><label for="conversion-name" class="panel-label">Member name</label><input id="conversion-name" name="name" value="{{ old('name', $trial->name) }}" class="panel-input mt-1"></div>
                        <div><label for="conversion-email" class="panel-label">Member email</label><input id="conversion-email" name="email" type="email" value="{{ old('email', $trial->email) }}" class="panel-input mt-1"></div>
                        <div><label for="conversion-phone" class="panel-label">Phone</label><input id="conversion-phone" name="phone" type="tel" value="{{ old('phone', $trial->phone) }}" class="panel-input mt-1"></div>
                        <div><label for="conversion-password" class="panel-label">Password for a new account</label><input id="conversion-password" name="password" type="password" autocomplete="new-password" class="panel-input mt-1"></div>
                        <div class="md:col-span-2"><label for="conversion-trainer" class="panel-label">Trainer</label><select id="conversion-trainer" name="assigned_trainer_user_id" class="panel-select mt-1">
                            <option value="">Assign trainer during conversion</option>
                            @foreach ($trainers as $trainer)
                                <option value="{{ $trainer->id }}" @selected(old('assigned_trainer_user_id', $trial->assigned_trainer_id) == $trainer->id)>{{ $trainer->name }}</option>
                            @endforeach
                        </select></div>
                        <div class="md:col-span-2"><label for="conversion-notes" class="panel-label">Conversion notes</label><textarea id="conversion-notes" name="notes" class="panel-textarea mt-1" rows="3">{{ old('notes') }}</textarea></div>
                        <div class="md:col-span-2">
                            <x-action-button type="submit">Convert to Member</x-action-button>
                        </div>
                        </form>
                    @elseif (in_array($trial->status, ['accepted', 'completed'], true) && $trial->linkedMemberHasGymProfile())
                        <div class="border-t border-slate-200 pt-6 dark:border-white/10">
                            <x-empty-state title="Already a gym member" message="This person is already present in the gym member list, so this trial cannot be converted again." />
                        </div>
                    @endif
                    @else
                        <x-empty-state title="Lead management is locked" message="Your role can view this lead, but trainer assignment and status changes require trial management permission for this branch." />
                    @endif
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
