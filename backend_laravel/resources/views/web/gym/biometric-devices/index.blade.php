@extends('layouts.panel')

@section('content')
    @php($scope = request()->only(['gym', 'branch']))
    <div class="space-y-6">
        <section class="panel-hero">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <span class="inline-flex rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold uppercase tracking-[.18em] text-violet-700 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-200">Device integrations</span>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Biometric Devices</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Connect face, fingerprint, palm, or card terminals. Atlas stores device-user mappings and attendance events—not biometric templates or face images.</p>
                </div>
                <a href="{{ route('web.gym.attendance.index', $scope) }}" class="panel-btn-secondary">Back to Attendance</a>
            </div>
        </section>

        @if (session('device_secret'))
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                <div class="font-semibold">Save this device secret now</div>
                <p class="mt-1 text-sm">@if(session('device_adapter') === 'essl_ebioserver') It is embedded in the one-time eBioServer webhook URL below. @else It is shown once. Configure it as <code>X-GymAtlas-Device-Token</code> on the connector. @endif</p>
                <div class="mt-3 grid gap-2 text-sm">
                    <div><span class="font-semibold">Device UUID:</span> <code class="break-all">{{ session('device_uuid') }}</code></div>
                    <div><span class="font-semibold">Secret:</span> <code class="break-all">{{ session('device_secret') }}</code></div>
                    @if(session('device_adapter') === 'essl_ebioserver')<div><span class="font-semibold">eBioServer webhook:</span> <code class="break-all">{{ url('/api/integrations/essl/ebioserver/'.session('device_uuid').'/'.session('device_secret')) }}</code><span class="mt-1 block font-sans">Configure one URL for this eBioServer connection. Atlas routes its registered terminals by serial number.</span></div>@else<div><span class="font-semibold">Event endpoint:</span> <code class="break-all">{{ url('/api/biometric/devices/'.session('device_uuid').'/events') }}</code></div>@endif
                    @if(session('webhook_encryption_password'))<div><span class="font-semibold">Webhook AES password:</span> <code class="break-all">{{ session('webhook_encryption_password') }}</code> <span class="font-sans">— paste this into eBioServer Utilities.</span></div>@endif
                </div>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(340px,.9fr)]">
            <x-premium-card class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="panel-section-title">Connected terminals</h2><p class="panel-section-copy">Each terminal has its own revocable credential and branch scope.</p></div>
                    <x-status-badge :label="$devices->count().' devices'" tone="info" />
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($devices as $device)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold text-slate-950 dark:text-white">{{ $device->name }}</h3>
                                        @php($effectiveStatus = $device->effectiveStatus())
                                        <x-status-badge :label="$effectiveStatus" :tone="in_array($effectiveStatus, ['online', 'connected'], true) ? 'success' : (in_array($effectiveStatus, ['offline', 'error'], true) ? 'danger' : ($device->is_active ? 'warning' : 'neutral'))" />
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $device->vendor }} {{ $device->model }} · {{ $device->branch->name }} · {{ str($device->connection_method)->replace('_', ' ')->title() }}</p>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $device->member_links_count }} mappings · Last contact {{ $device->last_seen_at?->diffForHumans() ?? 'never' }} · UUID <span class="font-mono">{{ $device->uuid }}</span></p>
                                    @if (abs((int) $device->clock_skew_seconds) > 300)<p class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300">Clock warning: terminal differs from Atlas by {{ gmdate('H:i:s', abs((int) $device->clock_skew_seconds)) }}.</p>@endif
                                    <details class="mt-3 text-sm text-slate-600 dark:text-slate-300"><summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">Setup instructions and gateway URLs</summary><p class="mt-2">{{ $deviceInstructions[$device->id] }}</p><div class="mt-2 space-y-1 font-mono text-xs">@if($device->adapter_key === 'essl_ebioserver')<div class="break-all">Webhook: {{ url('/api/integrations/essl/ebioserver/'.$device->uuid.'/{SECRET}') }}</div><div class="font-sans text-slate-500 dark:text-slate-400">Rotate the secret to generate a complete webhook URL, then paste it into eBioServer Utilities.</div>@else<div class="break-all">Events: {{ url('/api/biometric/devices/'.$device->uuid.'/events') }}</div><div class="break-all">Heartbeat: {{ url('/api/biometric/devices/'.$device->uuid.'/heartbeat') }}</div><div class="break-all">Commands: {{ url('/api/biometric/devices/'.$device->uuid.'/commands') }}</div>@endif</div></details>
                                    <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">Edit device and connector settings</summary><form method="POST" action="{{ route('web.gym.biometric-devices.update', ['device' => $device->id] + $scope) }}" class="mt-3 grid gap-3 md:grid-cols-2">@csrf @method('PUT')<x-form-input name="name" label="Device name" :value="$device->name" /><x-form-input name="vendor" label="Vendor" :value="$device->vendor" /><x-form-input name="model" label="Model" :value="$device->model" /><x-form-input name="firmware_version" label="Firmware" :value="$device->firmware_version" /><x-form-input name="serial_number" label="Serial number" :value="$device->serial_number" /><div><label class="panel-label">Methods</label><div class="grid grid-cols-2 gap-2">@foreach(['face' => 'Face', 'fingerprint' => 'Fingerprint', 'palm' => 'Palm', 'card' => 'Card'] as $value => $label)<label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200"><input type="checkbox" name="modalities[]" value="{{ $value }}" @checked(in_array($value, $device->modalities ?? [], true))>{{ $label }}</label>@endforeach</div></div><x-form-input name="configuration[host]" label="New host/IP" placeholder="Leave empty to keep current" /><x-form-input name="configuration[port]" label="New port" type="number" /><x-form-input name="configuration[server_url]" label="New eBioServer Webservice.asmx URL" /><x-form-input name="configuration[username]" label="New API username" /><x-form-input name="configuration[password]" label="New API password" type="password" /><x-form-input name="configuration[location_code]" label="New eBioServer location code" /><x-form-input name="configuration[webhook_encryption_password]" label="New webhook AES password (32 characters)" type="password" /><label class="panel-card-muted flex items-center gap-2 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"><input type="hidden" name="configuration[webhook_encryption_enabled]" value="0"><input type="checkbox" name="configuration[webhook_encryption_enabled]" value="1" @checked((bool) data_get($device->configuration, 'webhook_encryption_enabled', false))>Encrypted eBioServer webhook</label><div class="md:col-span-2"><button class="panel-btn-primary" type="submit">Save device settings</button></div></form>@if($device->adapter_key === 'essl_ebioserver')<form method="POST" action="{{ route('web.gym.biometric-devices.test-connection', ['device' => $device->id] + $scope) }}" class="mt-3">@csrf<button class="panel-btn-secondary" type="submit">Test eBioServer connection</button></form>@endif</details>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('web.gym.biometric-devices.rotate-secret', ['device' => $device->id] + $scope) }}">@csrf<button class="panel-btn-secondary" type="submit">Rotate secret</button></form>
                                    <form method="POST" action="{{ route('web.gym.biometric-devices.toggle', ['device' => $device->id] + $scope) }}">@csrf<button class="{{ $device->is_active ? 'panel-btn-danger' : 'panel-btn-secondary' }}" type="submit">{{ $device->is_active ? 'Disable' : 'Enable' }}</button></form>
                                </div>
                            </div>
                            @if ($device->last_error)<p class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-200">{{ $device->last_error }}</p>@endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-slate-700 dark:bg-slate-900/60"><h3 class="panel-section-title">No devices connected</h3><p class="panel-section-copy">Add the first terminal using the setup form.</p></div>
                    @endforelse
                </div>
            </x-premium-card>

            <x-premium-card class="p-6">
                <h2 class="panel-section-title">Add a device</h2>
                <p class="panel-section-copy">Choose the exact integration method supported by the machine.</p>
                <form id="biometric-device-create-form" method="POST" action="{{ route('web.gym.biometric-devices.store', $scope) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="gym_id" value="{{ $gym->id }}">
                    <x-form-select name="branch_id" label="Branch" :selected="old('branch_id', request('branch'))" :options="['' => 'Select branch'] + $branches->pluck('name', 'id')->all()" />
                    <x-form-input name="name" label="Device name" :value="old('name')" placeholder="Main entrance terminal" />
                    <div><label class="panel-label" for="adapter_key">Device type and connection</label><select class="panel-select" id="adapter_key" name="adapter_key" required><option value="">Select integration method</option>@foreach ($adapters as $key => $adapter)<option value="{{ $key }}" @selected(old('adapter_key') === $key)>{{ $adapter['label'] }}</option>@endforeach</select>@error('adapter_key')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                    <div class="grid gap-3 sm:grid-cols-2"><x-form-input name="vendor" label="Vendor override (optional)" :value="old('vendor')" placeholder="eSSL" /><x-form-input name="model" label="Exact model" :value="old('model')" placeholder="AI-Face Jupiter" /></div>
                    <div class="grid gap-3 sm:grid-cols-2"><x-form-input name="serial_number" label="Serial number" :value="old('serial_number')" /><x-form-input name="firmware_version" label="Firmware" :value="old('firmware_version')" /></div>
                    <div><label class="panel-label">Methods supported</label><div class="mt-2 grid grid-cols-2 gap-2">@foreach (['face' => 'Face', 'fingerprint' => 'Fingerprint', 'palm' => 'Palm', 'card' => 'Card'] as $value => $label)<label class="panel-card-muted flex items-center gap-2 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"><input type="checkbox" name="modalities[]" value="{{ $value }}" @checked(in_array($value, old('modalities', []), true))>{{ $label }}</label>@endforeach</div>@error('modalities')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                    <details class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800" open>
                        <summary class="cursor-pointer font-semibold text-slate-900 dark:text-white">Connector/server details</summary>
                        <div class="mt-4 space-y-3">
                            <div data-lan-fields class="grid gap-3 sm:grid-cols-2"><x-form-input name="configuration[host]" label="LAN host/IP" :value="old('configuration.host')" /><x-form-input name="configuration[port]" label="Port" type="number" :value="old('configuration.port')" /></div>
                            <x-form-input name="configuration[server_url]" label="Vendor server URL" :value="old('configuration.server_url')" placeholder="https://biometric.example.com/Webservice.asmx" />
                            <x-form-input name="configuration[username]" label="API username" :value="old('configuration.username')" />
                            <x-form-input name="configuration[password]" label="API password" type="password" />
                            <div data-ebio-fields class="space-y-3">
                                <div class="rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs text-violet-800 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-200">Use the public HTTPS <span class="font-mono">Webservice.asmx</span> URL. Atlas will verify both the API credentials and this terminal's serial number.</div>
                                <x-form-input name="configuration[location_code]" label="eBioServer location code" :value="old('configuration.location_code')" />
                                <x-form-input name="configuration[webhook_encryption_password]" label="Webhook AES password (optional; Atlas generates one when blank)" type="password" />
                                <label class="panel-card-muted flex items-center gap-2 px-3 py-2 text-sm text-slate-800 dark:text-slate-100"><input type="hidden" name="configuration[webhook_encryption_enabled]" value="0"><input type="checkbox" name="configuration[webhook_encryption_enabled]" value="1" @checked((bool) old('configuration.webhook_encryption_enabled', true))>Encrypt eBioServer webhook payloads</label>
                            </div>
                        </div>
                    </details>
                    <button class="panel-btn-primary w-full justify-center" type="submit">Create secure device</button>
                </form>
            </x-premium-card>
        </div>

        <x-premium-card class="p-6">
            <h2 class="panel-section-title">Recent device events</h2><p class="panel-section-copy">Unmatched or rejected events stay visible for diagnosis instead of silently disappearing.</p>
            <div class="mt-5 overflow-x-auto"><table class="panel-table"><thead><tr><th>Time</th><th>Device</th><th>Device user</th><th>Member</th><th>Method</th><th>Status</th><th>Result</th></tr></thead><tbody>
                @forelse ($recentEvents as $event)<tr><td>{{ $event->occurred_at_device->format('d M Y, h:i A') }}</td><td>{{ $event->device->name }}</td><td class="font-mono">{{ $event->external_user_id }}</td><td>{{ $event->memberLink?->memberProfile?->user?->name ?? 'Unmatched' }}</td><td>{{ ucfirst($event->modality ?? 'unknown') }}</td><td><x-status-badge :label="$event->status" :tone="match($event->status) { 'accepted' => 'success', 'unmatched', 'rejected' => 'danger', 'ignored' => 'neutral', default => 'warning' }" /></td><td class="min-w-72 max-w-sm text-sm text-slate-500 dark:text-slate-400"><span>{{ $event->error_message ?: 'Attendance recorded' }}</span>@if($event->status === 'unmatched')<details class="mt-2"><summary class="cursor-pointer font-semibold text-violet-700 dark:text-violet-300">Map to a member</summary><form method="POST" action="{{ route('web.gym.biometric-events.resolve', ['event' => $event->id] + $scope) }}" class="mt-3 space-y-3">@csrf<x-remote-user-search name="member_id" :field-id="'biometric_event_'.$event->id.'_member'" label="Atlas member" :search-url="route('web.gym.attendance.search.members', $scope + ['branch_id' => $event->branch_id])" placeholder="Search by name, email, or phone" required /><button class="panel-btn-primary" type="submit">Map and process event</button></form></details>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-center text-slate-500">No device events yet.</td></tr>@endforelse
            </tbody></table></div>
        </x-premium-card>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const adapter = document.getElementById('adapter_key');
            const form = document.getElementById('biometric-device-create-form');
            if (!adapter || !form) return;
            const toggleFields = () => {
                const isEbio = adapter.value === 'essl_ebioserver';
                document.querySelectorAll('[data-ebio-fields]').forEach((element) => element.classList.toggle('hidden', !isEbio));
                document.querySelectorAll('[data-lan-fields]').forEach((element) => element.classList.toggle('hidden', isEbio));
                ['serial_number', 'configuration[server_url]', 'configuration[username]', 'configuration[password]', 'configuration[location_code]']
                    .forEach((name) => form.querySelector(`[name="${name}"]`)?.toggleAttribute('required', isEbio));
            };
            adapter.addEventListener('change', toggleFields);
            toggleFields();
        });
    </script>
@endpush
