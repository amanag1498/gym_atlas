@extends('layouts.panel')

@section('content')
    @php
        $scope = request()->only(['gym', 'branch']);
        $totalHubs = $hubs->count();
        $onlineHubs = $hubs->filter(fn ($hub) => ($hubPayloads[$hub->id]['status'] ?? null) === 'online')->count();
        $needsSetup = $hubs->filter(fn ($hub) => in_array($hubPayloads[$hub->id]['status'] ?? null, ['pending', 'offline'], true))->count();
    @endphp

    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Smart Attendance hubs</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Monitor entrance hubs and manage their activation credentials.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="#create-hub" class="panel-btn-primary" onclick="document.getElementById('create-hub').open = true">Add hub</a>
                    <a href="{{ route('web.gym.attendance.index', $scope) }}" class="panel-btn-secondary">Attendance</a>
                    <a href="{{ route('web.gym.biometric-devices.index', $scope) }}" class="panel-btn-secondary">Biometric devices</a>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600 dark:text-slate-300" aria-label="Hub status summary">
            <span><strong class="text-slate-950 dark:text-white">{{ $totalHubs }}</strong> total</span>
            <span><strong class="text-emerald-700 dark:text-emerald-300">{{ $onlineHubs }}</strong> online</span>
            <span><strong class="text-amber-700 dark:text-amber-300">{{ $needsSetup }}</strong> need attention</span>
        </div>

        @if (session('smart_hub_secret'))
            <x-premium-card class="border-amber-300/70 bg-amber-50/90 p-5 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="text-sm font-semibold">Save this activation credential now</div>
                        <p class="mt-1 text-sm leading-6">This secret is shown once. Paste it into the Android hub app or ESP32 provisioning screen with the hub UUID.</p>
                    </div>
                    <x-status-badge label="One-time secret" tone="warning" />
                </div>
                <div class="mt-4 grid gap-3 text-sm lg:grid-cols-3">
                    <div class="rounded-xl border border-amber-200 bg-white/70 p-3 dark:border-amber-500/20 dark:bg-black/10"><span class="font-semibold">Hub UUID</span><code id="smart-hub-session-uuid" class="mt-1 block break-all">{{ session('smart_hub_uuid') }}</code></div>
                    <div class="rounded-xl border border-amber-200 bg-white/70 p-3 dark:border-amber-500/20 dark:bg-black/10"><span class="font-semibold">Public ID</span><code class="mt-1 block break-all">{{ session('smart_hub_public_id') }}</code></div>
                    <div class="rounded-xl border border-amber-200 bg-white/70 p-3 dark:border-amber-500/20 dark:bg-black/10"><span class="font-semibold">Device secret</span><code id="smart-hub-session-secret" class="mt-1 block break-all">{{ session('smart_hub_secret') }}</code></div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="panel-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('smart-hub-session-uuid').innerText)">Copy UUID</button>
                    <button type="button" class="panel-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('smart-hub-session-secret').innerText)">Copy secret</button>
                </div>
            </x-premium-card>
        @endif

        <div class="space-y-6">
            <x-premium-card class="overflow-hidden p-0">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="panel-section-title">Hub fleet</h2>
                            <p class="panel-section-copy">Check connection status and last contact. Open a hub for setup details or settings.</p>
                        </div>
                        <x-status-badge :label="$totalHubs.' hubs'" tone="info" />
                    </div>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($hubs as $hub)
                        @php
                            $payload = $hubPayloads[$hub->id];
                            $status = $payload['status'];
                            $tone = ! $hub->is_active ? 'neutral' : ($status === 'online' ? 'success' : ($status === 'offline' ? 'danger' : 'warning'));
                            $routeParams = array_merge(['hub' => $hub], $scope);
                            $activateUrl = url("/api/smart-attendance/hubs/{$hub->uuid}/activate");
                            $heartbeatUrl = url("/api/smart-attendance/hubs/{$hub->uuid}/heartbeat");
                            $configUrl = url("/api/smart-attendance/hubs/{$hub->uuid}/config");
                        @endphp
                        <article class="p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $hub->name }}</h3>
                                        <x-status-badge :label="str($status)->replace('_', ' ')->title()" :tone="$tone" />
                                        @unless($hub->is_active)<x-status-badge label="Disabled" tone="neutral" />@endunless
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $hub->branch?->name ?? 'Gym-wide' }} · {{ strtoupper($hub->platform) }} · Last heartbeat {{ $hub->last_seen_at?->diffForHumans() ?? 'never' }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2 lg:justify-end">
                                    <form method="POST" action="{{ route('web.gym.smart-attendance-hubs.toggle', $routeParams) }}">
                                        @csrf
                                        <x-action-button type="submit" variant="secondary">{{ $hub->is_active ? 'Disable' : 'Enable' }}</x-action-button>
                                    </form>
                                </div>
                            </div>

                            <details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">Setup details and credentials</summary>
                                <div class="mt-4 grid gap-3 text-sm">
                                    <p class="text-slate-600 dark:text-slate-300">Public ID <code class="break-all">{{ $hub->public_id }}</code> · Hub UUID <code class="break-all">{{ $hub->uuid }}</code></p>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[.16em] text-slate-500 dark:text-slate-400">Provisioning</div>
                                        <p class="mt-1 text-slate-600 dark:text-slate-300">Enter the hub UUID and the one-time secret shown after create or rotation. The device should activate once, then send heartbeat every 60 seconds.</p>
                                    </div>
                                    <div class="grid gap-2">
                                        <div class="flex flex-col gap-2 rounded-xl bg-white p-3 dark:bg-black/10 md:flex-row md:items-center md:justify-between"><code id="hub-{{ $hub->id }}-activate" class="break-all">{{ $activateUrl }}</code><button type="button" class="panel-btn-secondary !px-3" onclick="navigator.clipboard.writeText(document.getElementById('hub-{{ $hub->id }}-activate').innerText)">Copy</button></div>
                                        <div class="flex flex-col gap-2 rounded-xl bg-white p-3 dark:bg-black/10 md:flex-row md:items-center md:justify-between"><code id="hub-{{ $hub->id }}-heartbeat" class="break-all">{{ $heartbeatUrl }}</code><button type="button" class="panel-btn-secondary !px-3" onclick="navigator.clipboard.writeText(document.getElementById('hub-{{ $hub->id }}-heartbeat').innerText)">Copy</button></div>
                                        <div class="flex flex-col gap-2 rounded-xl bg-white p-3 dark:bg-black/10 md:flex-row md:items-center md:justify-between"><code id="hub-{{ $hub->id }}-config" class="break-all">{{ $configUrl }}</code><button type="button" class="panel-btn-secondary !px-3" onclick="navigator.clipboard.writeText(document.getElementById('hub-{{ $hub->id }}-config').innerText)">Copy</button></div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-white p-3 text-xs leading-5 text-slate-600 dark:border-slate-800 dark:bg-black/10 dark:text-slate-300">
                                        <div class="font-semibold text-slate-900 dark:text-white">Physical beacon profile</div>
                                        <div class="mt-1">UUID <code class="break-all">{{ \App\Services\SmartAttendance\SmartAttendanceHubService::BLE_SERVICE_UUID }}</code></div>
                                        <div>iBeacon major <code>{{ ($hub->id >> 16) & 0xffff }}</code> · minor <code>{{ $hub->id & 0xffff }}</code> · measured power <code>-59</code></div>
                                        <p class="mt-1 text-slate-500 dark:text-slate-400">Android and iOS Member apps accept this hardware profile. The authenticated config endpoint returns the same values plus compact V2 service data.</p>
                                    </div>
                                    <form method="POST" action="{{ route('web.gym.smart-attendance-hubs.rotate-secret', $routeParams) }}" data-confirm-submit data-confirm-title="Rotate hub secret?" data-confirm-message="The current hub credential will stop working until the device is updated." data-confirm-button="Rotate secret">
                                        @csrf
                                        <x-action-button type="submit" variant="secondary">Rotate secret</x-action-button>
                                    </form>
                                </div>
                            </details>

                            <details class="mt-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-900 dark:text-white">Edit hub</summary>
                                <form method="POST" action="{{ route('web.gym.smart-attendance-hubs.update', $routeParams) }}" class="mt-4 grid gap-3 md:grid-cols-5">
                                    @csrf
                                    @method('PUT')
                                    <label class="md:col-span-2">
                                        <span class="form-label">Name</span>
                                        <input class="form-input" name="name" value="{{ old('name', $hub->name) }}" required>
                                    </label>
                                    <label>
                                        <span class="form-label">Platform</span>
                                        <select class="form-input" name="platform" required>
                                            <option value="android" @selected(old('platform', $hub->platform) === 'android')>Android</option>
                                            <option value="esp32" @selected(old('platform', $hub->platform) === 'esp32')>ESP32</option>
                                            <option value="ble_hardware" @selected(old('platform', $hub->platform) === 'ble_hardware')>Other BLE hardware</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span class="form-label">Branch</span>
                                        <select class="form-input" name="branch_id">
                                            <option value="" @selected(old('branch_id', $hub->branch_id) === null)>Gym-wide</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" @selected((string) old('branch_id', $hub->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span class="form-label">Firmware</span>
                                        <input class="form-input" name="firmware_version" value="{{ old('firmware_version', $hub->firmware_version) }}" placeholder="Optional">
                                    </label>
                                    <div class="flex items-end md:col-start-5">
                                        <x-action-button type="submit" variant="secondary" class="w-full justify-center">Save changes</x-action-button>
                                    </div>
                                </form>
                            </details>
                        </article>
                    @empty
                        <div class="p-6">
                            <x-empty-state title="No Smart Attendance hubs yet" message="Create a hub for the entrance device you want to provision. The activation secret will be shown once after creation." />
                        </div>
                    @endforelse
                </div>
            </x-premium-card>

            <div class="space-y-4">
                <details id="create-hub" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" @if($errors->any()) open @endif>
                    <summary class="cursor-pointer text-base font-semibold text-slate-950 dark:text-white">Add a hub</summary>
                    <div class="mt-4 max-w-3xl">
                        <p class="panel-section-copy">Use one hub per reception phone, gate device, or ESP32 broadcaster.</p>
                        <form method="POST" action="{{ route('web.gym.smart-attendance-hubs.store', $scope) }}" class="mt-4 space-y-4">
                        @csrf
                        <label class="block">
                            <span class="form-label">Hub name</span>
                            <input class="form-input" name="name" value="{{ old('name') }}" placeholder="Reception Android Hub" required>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label>
                                <span class="form-label">Platform</span>
                                <select class="form-input" name="platform" required>
                                    <option value="android" @selected(old('platform', 'android') === 'android')>Android</option>
                                    <option value="esp32" @selected(old('platform') === 'esp32')>ESP32</option>
                                    <option value="ble_hardware" @selected(old('platform') === 'ble_hardware')>Other BLE hardware</option>
                                </select>
                            </label>
                            <label>
                                <span class="form-label">Branch</span>
                                <select class="form-input" name="branch_id">
                                    <option value="">Gym-wide</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="block">
                            <span class="form-label">Firmware version</span>
                            <input class="form-input" name="firmware_version" value="{{ old('firmware_version') }}" placeholder="Optional">
                        </label>
                        <x-action-button type="submit" class="w-full justify-center">Create hub and show secret</x-action-button>
                        </form>
                    </div>
                </details>

                <details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <summary class="cursor-pointer text-base font-semibold text-slate-950 dark:text-white">Setup checklist</summary>
                    <ol class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-200">1</span><span>Create a hub for the correct entrance branch.</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-200">2</span><span>Copy the UUID and one-time secret into the hub device.</span></li>
                        <li class="flex gap-3"><span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-200">3</span><span>Wait for activation and heartbeat to change the hub to online.</span></li>
                    </ol>
                    <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500 dark:bg-slate-900/70 dark:text-slate-400">The secret is never shown again after you leave the confirmation message. Rotate the secret if the device was configured incorrectly or moved.</p>
                </details>
            </div>
        </div>
    </div>
@endsection
