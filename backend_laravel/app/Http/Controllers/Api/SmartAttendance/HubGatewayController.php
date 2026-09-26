<?php

namespace App\Http\Controllers\Api\SmartAttendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmartAttendance\SmartAttendanceHubHeartbeatRequest;
use App\Services\SmartAttendance\SmartAttendanceHubService;
use Illuminate\Http\Request;

class HubGatewayController extends Controller
{
    public function __construct(private readonly SmartAttendanceHubService $hubs) {}

    public function activate(SmartAttendanceHubHeartbeatRequest $request, string $hubUuid)
    {
        $hub = $this->hubs->authenticate($hubUuid, $request->header('X-GymAtlas-Device-Token'));
        $hub = $this->hubs->activate($hub, $request->validated());

        return $this->success($this->hubs->config($hub), 'Smart Attendance Hub activated.');
    }

    public function heartbeat(SmartAttendanceHubHeartbeatRequest $request, string $hubUuid)
    {
        $hub = $this->hubs->authenticate($hubUuid, $request->header('X-GymAtlas-Device-Token'));
        $hub = $this->hubs->heartbeat($hub, $request->validated());

        return $this->success([
            'hub' => $this->hubs->payload($hub),
            'server_time' => now()->toIso8601String(),
            'next_heartbeat_seconds' => 60,
        ], 'Heartbeat accepted.');
    }

    public function config(Request $request, string $hubUuid)
    {
        $hub = $this->hubs->authenticate($hubUuid, $request->header('X-GymAtlas-Device-Token'));

        return $this->success($this->hubs->config($hub), 'Smart Attendance Hub config fetched.');
    }
}
