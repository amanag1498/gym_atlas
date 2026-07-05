@extends('layouts.panel')

@section('content')
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">Lead detail</p>
                    <h3 class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $trial->name }}</h3>
                    <p class="mt-3 max-w-2xl text-sm text-slate-300">Track follow-up, assign a trainer, and convert this lead into a member record when the trial is successful.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-status-badge :label="$trial->request_type === 'contact' ? 'Direct Enquiry' : 'Trial Request'" />
                    <x-status-badge :label="ucfirst($trial->status)" />
                    <x-action-button as="a" variant="secondary" href="{{ route('web.gym.trial-requests.index', request()->only(['gym', 'branch'])) }}">Back to Trials</x-action-button>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Lead profile</h3>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Lead type</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->request_type === 'contact' ? 'Direct enquiry' : 'Trial request' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Phone</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->phone ?: 'Not provided' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Email</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->email ?: 'Not provided' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Branch</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->branch?->name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Assigned trainer</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->assignedTrainer?->name ?? 'Not assigned' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Preferred slot</p>
                        <p class="mt-2 text-sm text-white">{{ optional($trial->preferred_date)->format('d M Y') ?: 'Not set' }}{{ $trial->preferred_time ? ' · '.substr((string) $trial->preferred_time, 0, 5) : '' }}</p>
                    </div>
                    <div class="panel-card-muted p-4">
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Linked member</p>
                        <p class="mt-2 text-sm text-white">{{ $trial->member?->name ?? 'Not linked yet' }}</p>
                    </div>
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h3 class="panel-section-title">Actions</h3>
                <div class="mt-6 grid gap-6">
                    <form method="POST" action="{{ route('web.gym.trial-requests.assign-trainer', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}" class="grid gap-4">
                        @csrf
                        <select name="assigned_trainer_id" class="panel-select">
                            <option value="">Assign trainer</option>
                            @foreach ($trainers as $trainer)
                                <option value="{{ $trainer->id }}" @selected($trial->assigned_trainer_id === $trainer->id)>{{ $trainer->name }}</option>
                            @endforeach
                        </select>
                        <textarea name="notes" class="panel-textarea" rows="3" placeholder="Follow-up notes">{{ $trial->notes }}</textarea>
                        <x-action-button type="submit">Save Assignment</x-action-button>
                    </form>

                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('web.gym.trial-requests.accept', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">
                            @csrf
                            <x-action-button type="submit">Accept</x-action-button>
                        </form>
                        <form method="POST" action="{{ route('web.gym.trial-requests.reject', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">
                            @csrf
                            <x-action-button type="submit" variant="danger">Reject</x-action-button>
                        </form>
                        <form method="POST" action="{{ route('web.gym.trial-requests.complete', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}">
                            @csrf
                            <x-action-button type="submit" variant="secondary">Mark Completed</x-action-button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('web.gym.trial-requests.convert', array_merge(request()->only(['gym', 'branch']), ['trial' => $trial->id])) }}" class="grid gap-4 md:grid-cols-2">
                        @csrf
                        <input name="name" value="{{ old('name', $trial->name) }}" class="panel-input" placeholder="Member name">
                        <input name="email" value="{{ old('email', $trial->email) }}" class="panel-input" placeholder="Member email">
                        <input name="phone" value="{{ old('phone', $trial->phone) }}" class="panel-input" placeholder="Phone">
                        <input name="password" class="panel-input" placeholder="Password for new user only">
                        <select name="assigned_trainer_user_id" class="panel-select md:col-span-2">
                            <option value="">Assign trainer during conversion</option>
                            @foreach ($trainers as $trainer)
                                <option value="{{ $trainer->id }}" @selected(old('assigned_trainer_user_id', $trial->assigned_trainer_id) == $trainer->id)>{{ $trainer->name }}</option>
                            @endforeach
                        </select>
                        <textarea name="notes" class="panel-textarea md:col-span-2" rows="3" placeholder="Conversion notes">{{ old('notes') }}</textarea>
                        <div class="md:col-span-2">
                            <x-action-button type="submit">Convert to Member</x-action-button>
                        </div>
                    </form>
                </div>
            </x-premium-card>
        </div>
    </div>
@endsection
