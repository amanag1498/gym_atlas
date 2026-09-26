<?php

namespace App\Http\Controllers\Web\Gym;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\SmartAttendance\StoreSmartAttendanceHubRequest;
use App\Http\Requests\SmartAttendance\UpdateSmartAttendanceHubRequest;
use App\Models\SmartAttendanceHub;
use App\Services\Audit\AuditLogService;
use App\Services\SmartAttendance\SmartAttendanceHubService;
use App\Services\Web\GymWebPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmartAttendanceHubController extends Controller
{
    public function __construct(
        private readonly GymWebPanelService $panel,
        private readonly SmartAttendanceHubService $hubs,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): View
    {
        $gym = $this->gym($request);
        $branchIds = $this->panel->accessibleBranchIds($request, $gym);
        $hubs = SmartAttendanceHub::query()->with('branch')
            ->where('gym_id', $gym->id)
            ->where(function ($query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->latest()
            ->get();

        return view('web.gym.smart-attendance-hubs.index', [
            'pageTitle' => 'Smart Attendance Hubs',
            'breadcrumbs' => ['Gym', 'Attendance', 'Smart Attendance Hubs'],
            'gym' => $gym,
            'branches' => $this->panel->accessibleBranches($request, $gym),
            'hubs' => $hubs,
            'hubPayloads' => $hubs->mapWithKeys(fn (SmartAttendanceHub $hub): array => [$hub->id => $this->hubs->payload($hub)]),
        ]);
    }

    public function store(StoreSmartAttendanceHubRequest $request): RedirectResponse
    {
        $gym = $this->gym($request);
        if ($request->filled('branch_id')) {
            $branch = $this->panel->accessibleBranches($request, $gym)->firstWhere('id', $request->integer('branch_id'));
            abort_unless($branch, 404);
        }
        $result = $this->hubs->create($request->validated(), $gym, $request->user());
        $this->audit->log('web.gym.smart_attendance_hub.created', 'create', $request, $result['hub'], $gym, $result['hub']->branch, newValues: $result['hub']->toArray());

        return redirect()->route('web.gym.smart-attendance-hubs.index', $request->only(['gym', 'branch']))
            ->with('status', 'Smart Attendance Hub created. Save the activation secret now; it will not be shown again.')
            ->with('smart_hub_secret', $result['secret'])
            ->with('smart_hub_uuid', $result['hub']->uuid)
            ->with('smart_hub_public_id', $result['hub']->public_id);
    }

    public function update(UpdateSmartAttendanceHubRequest $request, SmartAttendanceHub $hub): RedirectResponse
    {
        $gym = $this->authorizeHub($request, $hub);
        if ($request->filled('branch_id')) {
            $branch = $this->panel->accessibleBranches($request, $gym)->firstWhere('id', $request->integer('branch_id'));
            abort_unless($branch, 404);
        }
        $old = $hub->toArray();
        $hub = $this->hubs->update($hub, $request->validated(), $gym);
        $this->audit->log('web.gym.smart_attendance_hub.updated', 'update', $request, $hub, $gym, $hub->branch, $old, $hub->toArray());

        return back()->with('status', 'Smart Attendance Hub updated.');
    }

    public function toggle(Request $request, SmartAttendanceHub $hub): RedirectResponse
    {
        $gym = $this->authorizeHub($request, $hub);
        $old = $hub->is_active;
        $hub = $this->hubs->toggle($hub);
        $this->audit->log('web.gym.smart_attendance_hub.toggled', 'update', $request, $hub, $gym, $hub->branch, ['is_active' => $old], ['is_active' => $hub->is_active]);

        return back()->with('status', $hub->is_active ? 'Smart Attendance Hub enabled.' : 'Smart Attendance Hub disabled.');
    }

    public function rotateSecret(Request $request, SmartAttendanceHub $hub): RedirectResponse
    {
        $gym = $this->authorizeHub($request, $hub);
        $secret = $this->hubs->rotateSecret($hub);
        $hub = $hub->fresh(['branch']);
        $this->audit->log('web.gym.smart_attendance_hub.secret_rotated', 'update', $request, $hub, $gym, $hub->branch);

        return back()->with('status', 'Smart Attendance Hub secret rotated. Update the hub immediately.')
            ->with('smart_hub_secret', $secret)
            ->with('smart_hub_uuid', $hub->uuid)
            ->with('smart_hub_public_id', $hub->public_id);
    }

    private function gym(Request $request)
    {
        $gym = $this->panel->resolveGym($request);
        $this->panel->assertPermission($request, PermissionName::AttendanceManage->value, $gym);

        return $gym;
    }

    private function authorizeHub(Request $request, SmartAttendanceHub $hub)
    {
        $gym = $this->gym($request);
        abort_unless((int) $hub->gym_id === (int) $gym->id, 404);
        if ($hub->branch) {
            $this->panel->assertBranchAccessible($hub->branch, $request, $gym);
        }

        return $gym;
    }
}
