<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Biometric\StoreBiometricDeviceRequest;
use App\Http\Requests\Biometric\UpdateBiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Models\BiometricDeviceEvent;
use App\Models\Branch;
use App\Models\MemberProfile;
use App\Services\Audit\AuditLogService;
use App\Services\Biometric\BiometricAdapterCatalog;
use App\Services\Biometric\BiometricEnrollmentService;
use App\Services\Biometric\BiometricEventIngestionService;
use App\Services\Biometric\EbioServerClient;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiometricDeviceController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $panel,
        private readonly BiometricAdapterCatalog $catalog,
        private readonly BiometricEnrollmentService $enrollments,
        private readonly BiometricEventIngestionService $ingestion,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gym($request);
        $branchIds = $this->panel->accessibleBranchIds($request, $gym);
        $devices = BiometricDevice::query()->withCount('memberLinks')->with('branch')
            ->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->latest()->get();

        return view('web.gym.biometric-devices.index', [
            'pageTitle' => 'Biometric Devices',
            'breadcrumbs' => ['Gym', 'Attendance', 'Biometric Devices'],
            'gym' => $gym,
            'branches' => $this->panel->accessibleBranches($request, $gym),
            'adapters' => $this->catalog->all(),
            'devices' => $devices,
            'recentEvents' => BiometricDeviceEvent::query()->with(['device', 'memberLink.memberProfile.user'])
                ->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->latest()->take(25)->get(),
            'deviceInstructions' => $devices->mapWithKeys(
                fn (BiometricDevice $device): array => [$device->id => $this->enrollments->instructions($device)]
            ),
        ]);
    }

    public function store(StoreBiometricDeviceRequest $request): RedirectResponse
    {
        $gym = $this->gym($request);
        abort_unless((int) $request->integer('gym_id') === (int) $gym->id, 404);
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        $this->panel->assertBranchAccessible($branch, $request, $gym);
        $result = $this->enrollments->createDevice($request->validated(), $request->user());
        $this->audit->log('web.gym.biometric_device.created', 'create', $request, $result['device'], $gym, $branch, newValues: $result['device']->toArray());

        return redirect()->route('web.gym.biometric-devices.index', request()->only(['gym', 'branch']))
            ->with('status', 'Biometric device added. Save the device secret now; it will not be shown again.')
            ->with('device_secret', $result['secret'])
            ->with('device_uuid', $result['device']->uuid)
            ->with('device_adapter', $result['device']->adapter_key)
            ->with('webhook_encryption_password', $result['webhook_encryption_password']);
    }

    public function rotateSecret(Request $request, BiometricDevice $device): RedirectResponse
    {
        $gym = $this->authorizeDevice($request, $device);
        $secret = $this->enrollments->rotateSecret($device);
        $this->audit->log('web.gym.biometric_device.secret_rotated', 'update', $request, $device, $gym, $device->branch);

        return back()->with('status', 'Device secret rotated. Update the connector immediately.')
            ->with('device_secret', $secret)->with('device_uuid', $device->uuid)
            ->with('device_adapter', $device->adapter_key);
    }

    public function update(UpdateBiometricDeviceRequest $request, BiometricDevice $device): RedirectResponse
    {
        $gym = $this->authorizeDevice($request, $device);
        $old = $device->toArray();
        $device = $this->enrollments->updateDevice($device, $request->validated());
        $this->audit->log('web.gym.biometric_device.updated', 'update', $request, $device, $gym, $device->branch, $old, $device->toArray());

        return back()->with('status', 'Biometric device settings updated.');
    }

    public function toggle(Request $request, BiometricDevice $device): RedirectResponse
    {
        $gym = $this->authorizeDevice($request, $device);
        $old = $device->is_active;
        $device->forceFill(['is_active' => ! $old, 'status' => $old ? 'disabled' : 'pending'])->save();
        $this->audit->log('web.gym.biometric_device.toggled', 'update', $request, $device, $gym, $device->branch, ['is_active' => $old], ['is_active' => $device->is_active]);

        return back()->with('status', $device->is_active ? 'Device enabled.' : 'Device disabled. New events will be rejected.');
    }

    public function testConnection(Request $request, BiometricDevice $device, EbioServerClient $client): RedirectResponse
    {
        $this->authorizeDevice($request, $device);
        abort_unless($device->adapter_key === 'essl_ebioserver', 422, 'Connection testing is available for eBioServer devices.');
        $result = $client->testConnection($device);

        $device->forceFill([
            'status' => $result['ok'] ? 'connected' : 'error',
            'last_seen_at' => $result['ok'] ? now() : $device->last_seen_at,
            'last_error' => $result['ok'] ? null : $result['message'],
        ])->save();

        return $result['ok']
            ? back()->with('status', $result['message'])
            : back()->withErrors(['connection' => $result['message']]);
    }

    public function resolveEvent(Request $request, BiometricDeviceEvent $event): RedirectResponse
    {
        $gym = $this->gym($request);
        abort_unless((int) $event->gym_id === (int) $gym->id, 404);
        $this->panel->assertBranchAccessible($event->branch, $request, $gym);
        $data = $request->validate(['member_id' => ['required', 'integer', 'exists:users,id']]);
        $profile = MemberProfile::query()
            ->where('gym_id', $gym->id)
            ->where('user_id', $data['member_id'])
            ->firstOrFail();
        $event = $this->ingestion->resolveUnmatched($event, $profile, $request->user());
        $this->audit->log(
            'web.gym.biometric_event.resolved',
            'update',
            $request,
            $event,
            $gym,
            $event->branch,
            context: ['member_profile_id' => $profile->id, 'attendance_log_id' => $event->attendance_log_id],
        );

        return back()->with('status', $event->status === 'accepted'
            ? 'Device user mapped and attendance recorded.'
            : 'Device user mapped. Attendance result: '.$event->status.'.');
    }

    private function gym(Request $request)
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::AttendanceManage->value, $gym);

        return $gym;
    }

    private function authorizeDevice(Request $request, BiometricDevice $device)
    {
        $gym = $this->gym($request);
        abort_unless((int) $device->gym_id === (int) $gym->id, 404);
        $this->panel->assertBranchAccessible($device->branch, $request, $gym);

        return $gym;
    }
}
