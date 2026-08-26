<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biometric\EnrollMemberBiometricRequest;
use App\Http\Requests\Biometric\StoreBiometricDeviceRequest;
use App\Http\Requests\Biometric\UpdateBiometricDeviceRequest;
use App\Models\BiometricDevice;
use App\Models\BiometricMemberLink;
use App\Models\Branch;
use App\Models\MemberProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\Biometric\BiometricAdapterCatalog;
use App\Services\Biometric\BiometricEnrollmentService;
use Illuminate\Http\Request;

class BiometricDeviceController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scope,
        private readonly BiometricAdapterCatalog $catalog,
        private readonly BiometricEnrollmentService $enrollments,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request)
    {
        $gym = $this->scope->resolveGym($request, true);
        $branchIds = $this->scope->branchesQuery($request->user())->where('gym_id', $gym->id)->pluck('branches.id');
        $devices = BiometricDevice::query()->with('branch:id,name')->withCount('memberLinks')
            ->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)->latest()->get();

        return $this->success([
            'adapters' => $this->catalog->all(),
            'devices' => $devices->map(fn (BiometricDevice $device): array => $this->devicePayload($device)),
        ], 'Biometric devices fetched successfully.');
    }

    public function store(StoreBiometricDeviceRequest $request)
    {
        $gym = $this->scope->resolveGym($request, true);
        abort_unless((int) $request->integer('gym_id') === (int) $gym->id, 404);
        $branch = Branch::query()->findOrFail($request->integer('branch_id'));
        abort_unless((int) $branch->gym_id === (int) $gym->id && $this->scope->canAccessBranch($request->user(), $branch), 404);
        $result = $this->enrollments->createDevice($request->validated(), $request->user());
        $this->audit->log('gym.biometric_device.created', 'create', $request, $result['device'], $gym, $branch, newValues: $result['device']->toArray());

        return $this->success([
            'device' => $this->devicePayload($result['device']->load('branch')),
            'device_secret' => $result['secret'],
            'event_endpoint' => url("/api/biometric/devices/{$result['device']->uuid}/events"),
            'ebioserver_webhook_url' => $result['device']->adapter_key === 'essl_ebioserver'
                ? url("/api/integrations/essl/ebioserver/{$result['device']->uuid}/{$result['secret']}")
                : null,
            'ebioserver_webhook_encryption_password' => $result['webhook_encryption_password'],
        ], 'Biometric device created. Store the secret now; it will not be returned again.', 201);
    }

    public function member(Request $request, User $member)
    {
        [$gym, $profile] = $this->profile($request, $member);
        $links = $profile->biometricMemberLinks()->with('device.branch')->latest()->get();

        return $this->success([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'links' => $links->map(fn (BiometricMemberLink $link): array => $this->linkPayload($link)),
        ], 'Member biometric setup fetched successfully.');
    }

    public function update(UpdateBiometricDeviceRequest $request, BiometricDevice $device)
    {
        $gym = $this->scope->resolveGym($request, true);
        abort_unless((int) $device->gym_id === (int) $gym->id && $this->scope->canAccessBranch($request->user(), $device->branch_id), 404);
        $old = $device->toArray();
        $device = $this->enrollments->updateDevice($device, $request->validated());
        $this->audit->log('gym.biometric_device.updated', 'update', $request, $device, $gym, $device->branch, $old, $device->toArray());

        return $this->success($this->devicePayload($device), 'Biometric device updated.');
    }

    public function enroll(EnrollMemberBiometricRequest $request, User $member)
    {
        [$gym, $profile] = $this->profile($request, $member);
        $branchIds = $this->scope->branchesQuery($request->user())->where('gym_id', $gym->id)->pluck('branches.id');
        $devices = BiometricDevice::query()->where('gym_id', $gym->id)->whereIn('branch_id', $branchIds)
            ->whereIn('id', $request->validated('device_ids'))->get()->all();
        abort_unless(count($devices) === count($request->validated('device_ids')), 404);
        $links = $this->enrollments->enroll($profile->loadMissing('user'), $devices, $request->validated('modalities'), $request->user());
        $this->audit->log('gym.member_biometric.requested', 'create', $request, $profile, $gym, $profile->branch, context: ['link_ids' => collect($links)->pluck('id')->all()]);

        return $this->success(collect($links)->map(fn (BiometricMemberLink $link): array => $this->linkPayload($link)), 'Biometric setup prepared.', 201);
    }

    public function confirm(Request $request, BiometricMemberLink $link)
    {
        [$gym] = $this->authorizeLink($request, $link);
        $link = $this->enrollments->confirm($link);
        $this->audit->log('gym.member_biometric.confirmed', 'update', $request, $link, $gym, $link->branch);

        return $this->success($this->linkPayload($link), 'Biometric enrollment confirmed.');
    }

    public function revoke(Request $request, BiometricMemberLink $link)
    {
        [$gym] = $this->authorizeLink($request, $link);
        $link = $this->enrollments->revoke($link->loadMissing(['device', 'memberProfile']));
        $this->audit->log('gym.member_biometric.revoked', 'update', $request, $link, $gym, $link->branch);

        return $this->success($this->linkPayload($link), 'Biometric access revoked.');
    }

    private function profile(Request $request, User $member): array
    {
        $gym = $this->scope->resolveGym($request, true);
        $profile = MemberProfile::query()->with('branch')->where('gym_id', $gym->id)->where('user_id', $member->id)->firstOrFail();
        if ($profile->branch_id) {
            abort_unless($this->scope->canAccessBranch($request->user(), $profile->branch_id), 404);
        }

        return [$gym, $profile];
    }

    private function authorizeLink(Request $request, BiometricMemberLink $link): array
    {
        $link->loadMissing(['branch', 'memberProfile.user', 'device']);
        [$gym, $profile] = $this->profile($request, $link->memberProfile->user);
        abort_unless((int) $link->gym_id === (int) $gym->id && (int) $link->member_profile_id === (int) $profile->id, 404);

        return [$gym, $profile];
    }

    private function devicePayload(BiometricDevice $device): array
    {
        return [
            'id' => $device->id, 'uuid' => $device->uuid, 'gym_id' => $device->gym_id,
            'branch_id' => $device->branch_id, 'branch_name' => $device->branch?->name,
            'name' => $device->name, 'vendor' => $device->vendor, 'model' => $device->model,
            'firmware_version' => $device->firmware_version, 'serial_number' => $device->serial_number,
            'adapter_key' => $device->adapter_key, 'connection_method' => $device->connection_method,
            'modalities' => $device->modalities, 'capabilities' => $device->capabilities,
            'status' => $device->effectiveStatus(), 'stored_status' => $device->status, 'is_active' => $device->is_active,
            'member_links_count' => $device->member_links_count ?? null,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_event_at' => $device->last_event_at?->toIso8601String(),
            'connector_version' => $device->connector_version,
            'clock_skew_seconds' => $device->clock_skew_seconds,
            'last_error' => $device->last_error,
        ];
    }

    private function linkPayload(BiometricMemberLink $link): array
    {
        return [
            'id' => $link->id, 'device_id' => $link->biometric_device_id,
            'device_name' => $link->device?->name, 'external_user_id' => $link->external_user_id,
            'modalities' => $link->modalities, 'enrollment_method' => $link->enrollment_method,
            'status' => $link->status, 'sync_error' => $link->sync_error,
            'enrolled_at' => $link->enrolled_at?->toIso8601String(),
            'last_synced_at' => $link->last_synced_at?->toIso8601String(),
        ];
    }
}
