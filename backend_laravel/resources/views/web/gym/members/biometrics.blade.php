@extends('layouts.panel')

@section('content')
    @php($scope = request()->only(['gym', 'branch']))
    <div class="space-y-6">
        <section class="panel-hero"><div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-200">Member access</span><h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Biometric setup for {{ $member->name }}</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Atlas generates the machine ID and requests supported captures remotely. The member completes the prompt on the terminal; biometric templates never enter Atlas.</p></div><a href="{{ route('web.gym.members.show', ['member' => $member->id] + $scope) }}" class="panel-btn-secondary">Back to Member</a></div></section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(340px,.8fr)]">
            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Enrollment status</h2><p class="panel-section-copy">A member may have a separate mapping and status on every terminal.</p>
                <div class="mt-5 space-y-3">
                    @forelse ($links as $link)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950 dark:text-white">{{ $link->device->name }}</h3><x-status-badge :label="str($link->status)->replace('_', ' ')->title()" :tone="match($link->status) { 'enrolled' => 'success', 'sync_error', 'revoked' => 'danger', default => 'warning' }" /></div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $link->device->branch->name }} · Device User ID <span class="font-mono font-semibold text-slate-900 dark:text-white">{{ $link->external_user_id }}</span></p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $instructions[$link->id] }}</p>@if($link->sync_error)<p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $link->sync_error }}</p>@endif</div><div class="flex flex-wrap gap-2">@if($link->status !== 'enrolled' && $link->status !== 'revoked')<form method="POST" action="{{ route('web.gym.members.biometrics.confirm', ['link' => $link->id] + $scope) }}">@csrf<button class="panel-btn-primary" type="submit">Confirm capture</button></form>@endif @if($link->status !== 'revoked')<form method="POST" action="{{ route('web.gym.members.biometrics.revoke', ['link' => $link->id] + $scope) }}" data-confirm-submit data-confirm-title="Revoke biometric access?" data-confirm-message="Future scans from this device mapping will be rejected.">@csrf<button class="panel-btn-danger" type="submit">Revoke</button></form>@endif</div></div>
                        </div>
                    @empty<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-slate-700 dark:bg-slate-900/60"><h3 class="panel-section-title">Not enrolled on a device</h3><p class="panel-section-copy">Select one or more branch devices to begin.</p></div>@endforelse
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Set up on devices</h2><p class="panel-section-copy">Choose compatible devices and capture methods. Atlas handles the IDs and mappings.</p>
                @if ($devices->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-slate-700 dark:bg-slate-900/60"><p class="panel-section-copy">No active biometric device is available for {{ $memberProfile->branch?->name ?? 'this gym' }}.</p><a class="panel-btn-primary mt-4" href="{{ route('web.gym.biometric-devices.index', $scope) }}">Add a device</a></div>
                @else
                    <form method="POST" action="{{ route('web.gym.members.biometrics.store', ['member' => $member->id] + $scope) }}" class="mt-5 space-y-5">@csrf
                        <div><label class="panel-label">Devices</label><div class="mt-2 space-y-2">@foreach($devices as $device)<label class="panel-card-muted flex items-start gap-3 px-4 py-3"><input class="mt-1" type="checkbox" name="device_ids[]" value="{{ $device->id }}" @checked(in_array($device->id, old('device_ids', [])))><span><span class="block font-semibold text-slate-950 dark:text-white">{{ $device->name }}</span><span class="text-sm text-slate-500 dark:text-slate-400">{{ $device->vendor }} {{ $device->model }} · {{ $device->branch->name }} · {{ collect($device->modalities)->map(fn($item) => ucfirst($item))->join(', ') }}</span></span></label>@endforeach</div>@error('device_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <div><label class="panel-label">Capture methods</label><div class="mt-2 grid grid-cols-2 gap-2">@foreach(['face' => 'Face', 'fingerprint' => 'Fingerprint', 'palm' => 'Palm', 'card' => 'Card'] as $value => $label)<label class="panel-card-muted flex items-center gap-2 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"><input type="checkbox" name="modalities[]" value="{{ $value }}" @checked(in_array($value, old('modalities', [])))>{{ $label }}</label>@endforeach</div>@error('modalities')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                        <button class="panel-btn-primary w-full justify-center" type="submit">Prepare biometric setup</button>
                    </form>
                @endif
            </x-premium-card>
        </div>
    </div>
@endsection
