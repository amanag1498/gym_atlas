<?php

namespace App\Http\Controllers\Api\Gym\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmartAttendance\StoreSmartAttendanceHubRequest;
use App\Http\Requests\SmartAttendance\UpdateSmartAttendanceHubRequest;
use App\Models\SmartAttendanceHub;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\ScopeResolver;
use App\Services\SmartAttendance\SmartAttendanceHubService;
use Illuminate\Http\Request;

class SmartAttendanceHubController extends Controller
{
    public function __construct(
        private readonly ScopeResolver $scope,
        private readonly SmartAttendanceHubService $hubs,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request)
    {
        $gym = $this->scope->resolveGym($request, true);
        $branchIds = $this->scope->branchesQuery($request->user())->where('gym_id', $gym->id)->pluck('branches.id')->all();
        $hubs = SmartAttendanceHub::query()->with('branch:id,name')
            ->where('gym_id', $gym->id)
            ->where(function ($query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->latest()
            ->get();

        return $this->success([
            'hubs' => $hubs->map(fn (SmartAttendanceHub $hub): array => $this->hubs->payload($hub)),
        ], 'Smart Attendance Hubs fetched successfully.');
    }

    public function store(StoreSmartAttendanceHubRequest $request)
    {
        $gym = $this->scope->resolveGym($request, true);
        if ($request->filled('branch_id')) {
            abort_unless($this->scope->canAccessBranch($request->user(), (int) $request->integer('branch_id')), 404);
        }

        $result = $this->hubs->create($request->validated(), $gym, $request->user());
        $this->audit->log('gym.smart_attendance_hub.created', 'create', $request, $result['hub'], $gym, $result['hub']->branch, newValues: $result['hub']->toArray());

        return $this->success([
            'hub' => $this->hubs->payload($result['hub']),
            'device_secret' => $result['secret'],
            'activation_endpoint' => url("/api/smart-attendance/hubs/{$result['hub']->uuid}/activate"),
            'heartbeat_endpoint' => url("/api/smart-attendance/hubs/{$result['hub']->uuid}/heartbeat"),
            'config_endpoint' => url("/api/smart-attendance/hubs/{$result['hub']->uuid}/config"),
        ], 'Smart Attendance Hub created. Store the secret now; it will not be returned again.', 201);
    }

    public function update(UpdateSmartAttendanceHubRequest $request, SmartAttendanceHub $hub)
    {
        $gym = $this->authorizeHub($request, $hub);
        if ($request->filled('branch_id')) {
            abort_unless($this->scope->canAccessBranch($request->user(), (int) $request->integer('branch_id')), 404);
        }
        $old = $hub->toArray();
        $hub = $this->hubs->update($hub, $request->validated(), $gym);
        $this->audit->log('gym.smart_attendance_hub.updated', 'update', $request, $hub, $gym, $hub->branch, $old, $hub->toArray());

        return $this->success($this->hubs->payload($hub), 'Smart Attendance Hub updated.');
    }

    public function toggle(Request $request, SmartAttendanceHub $hub)
    {
        $gym = $this->authorizeHub($request, $hub);
        $old = $hub->is_active;
        $hub = $this->hubs->toggle($hub);
        $this->audit->log('gym.smart_attendance_hub.toggled', 'update', $request, $hub, $gym, $hub->branch, ['is_active' => $old], ['is_active' => $hub->is_active]);

        return $this->success($this->hubs->payload($hub), $hub->is_active ? 'Smart Attendance Hub enabled.' : 'Smart Attendance Hub disabled.');
    }

    public function rotateSecret(Request $request, SmartAttendanceHub $hub)
    {
        $gym = $this->authorizeHub($request, $hub);
        $secret = $this->hubs->rotateSecret($hub);
        $hub = $hub->fresh(['branch']);
        $this->audit->log('gym.smart_attendance_hub.secret_rotated', 'update', $request, $hub, $gym, $hub->branch);

        return $this->success([
            'hub' => $this->hubs->payload($hub),
            'device_secret' => $secret,
        ], 'Smart Attendance Hub secret rotated. Update the hub immediately.');
    }

    private function authorizeHub(Request $request, SmartAttendanceHub $hub)
    {
        $gym = $this->scope->resolveGym($request, true);
        abort_unless((int) $hub->gym_id === (int) $gym->id, 404);
        if ($hub->branch_id !== null) {
            abort_unless($this->scope->canAccessBranch($request->user(), (int) $hub->branch_id), 404);
        }

        return $gym;
    }
}
