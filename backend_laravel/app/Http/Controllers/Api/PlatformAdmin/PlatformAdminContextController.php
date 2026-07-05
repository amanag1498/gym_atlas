<?php

namespace App\Http\Controllers\Api\PlatformAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\ActivityLog;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;

class PlatformAdminContextController extends Controller
{
    public function __invoke(Request $request)
    {
        return $this->success([
            'user' => UserResource::make($request->user()),
            'stats' => [
                'users_count' => User::count(),
                'gyms_count' => Gym::count(),
                'activity_logs_count' => ActivityLog::count(),
            ],
        ]);
    }
}
