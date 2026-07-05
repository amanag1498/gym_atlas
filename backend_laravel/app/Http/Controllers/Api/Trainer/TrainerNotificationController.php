<?php

namespace App\Http\Controllers\Api\Trainer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Trainer\TrainerNotificationResource;
use App\Models\Notification;
use App\Services\Trainer\TrainerScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrainerNotificationController extends Controller
{
    public function __construct(
        private readonly TrainerScopeService $trainerScopeService,
    ) {
    }

    public function index(Request $request)
    {
        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $paginator = $this->trainerScopeService->notificationsQuery($trainerProfile)
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->latest('created_at')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->paginated($paginator, TrainerNotificationResource::collection($paginator->getCollection()), 'Trainer notifications fetched successfully.');
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($request->user()->active_role === \App\Enums\RoleName::Trainer->value, 403);

        $trainerProfile = $this->trainerScopeService->resolveTrainerProfile($request);
        $allowed = $this->trainerScopeService->notificationsQuery($trainerProfile)->whereKey($notification->id)->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([
                'notification_id' => ['You do not have access to this notification.'],
            ]);
        }

        $notification->forceFill(['read_at' => now()])->save();

        return $this->success(TrainerNotificationResource::make($notification->fresh()), 'Notification marked as read.');
    }
}
