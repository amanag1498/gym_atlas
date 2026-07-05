<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Notification\NotificationPreferenceCatalogService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationPreferenceCatalogService $catalogService,
    ) {
    }

    public function index(Request $request)
    {
        $paginator = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->filled('read'), function ($query) use ($request): void {
                $read = filter_var($request->query('read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($read === true) {
                    $query->whereNotNull('read_at');
                } elseif ($read === false) {
                    $query->whereNull('read_at');
                }
            })
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return $this->paginated($paginator, NotificationResource::collection($paginator->getCollection()), 'Notifications fetched successfully.');
    }

    public function markRead(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        return $this->success(
            NotificationResource::make($this->notificationService->markRead($notification)),
            'Notification marked as read.'
        );
    }

    public function markUnread(Request $request, Notification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        return $this->success(
            NotificationResource::make($this->notificationService->markUnread($notification)),
            'Notification marked as unread.'
        );
    }

    public function markAllRead(Request $request)
    {
        $count = $this->notificationService->markAllRead(
            $request->user(),
            $request->integer('gym_id') ?: null,
            $request->integer('branch_id') ?: null,
        );

        return $this->success([
            'marked_count' => $count,
        ], 'Notifications marked as read.');
    }

    public function preferences(Request $request)
    {
        return $this->success(
            $this->catalogService->forUser($request->user()),
            'Notification preferences fetched successfully.'
        );
    }

    public function updatePreferences(UpdateNotificationPreferenceRequest $request)
    {
        foreach ($request->validated('preferences') as $preference) {
            NotificationPreference::query()->updateOrCreate([
                'user_id' => $request->user()->id,
                'gym_id' => $preference['gym_id'] ?? null,
                'branch_id' => $preference['branch_id'] ?? null,
                'notification_type' => $preference['notification_type'],
            ], [
                'is_enabled' => $preference['is_enabled'],
            ]);
        }

        return $this->success(
            $this->catalogService->forUser($request->user()),
            'Notification preferences updated successfully.'
        );
    }
}
