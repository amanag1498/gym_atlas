<?php

namespace App\Http\Controllers\Api\Chat;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\MemberProfile;
use App\Models\Notification;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainerMemberChatController extends Controller
{
    public function __construct(private readonly FcmNotificationService $fcmNotificationService) {}

    public function conversations(Request $request)
    {
        $user = $request->user();

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = TrainerProfile::query()->where('user_id', $user->id)->firstOrFail();
            $profiles = MemberProfile::query()
                ->with('user')
                ->where('assigned_trainer_user_id', $user->id)
                ->where('gym_id', $trainerProfile->gym_id)
                ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
                ->get();

            $memberIds = $profiles->pluck('user_id')->all();
            $this->ensureTrainerConversations($user->id, $memberIds);

            $conversations = ChatConversation::query()
                ->with(['trainer', 'member', 'lastMessage'])
                ->where('trainer_id', $user->id)
                ->whereIn('member_id', $memberIds)
                ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at)'))
                ->get();

            return $this->success(ChatConversationResource::collection($conversations), 'Chat conversations fetched successfully.');
        }

        if ($user->active_role === RoleName::Member->value) {
            $profile = MemberProfile::query()
                ->where('user_id', $user->id)
                ->whereNotNull('assigned_trainer_user_id')
                ->first();

            if (! $profile) {
                return $this->success([], 'No assigned trainer conversation found.');
            }

            $trainer = User::query()->find($profile->assigned_trainer_user_id);
            if (! $trainer) {
                return $this->success([], 'No assigned trainer conversation found.');
            }

            $conversation = $this->ensureConversation($trainer->id, $user->id)
                ->load(['trainer', 'member', 'lastMessage']);

            return $this->success(ChatConversationResource::collection(collect([$conversation])), 'Chat conversations fetched successfully.');
        }

        abort(403, 'This role cannot access trainer-member chat.');
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('chat.max_messages_per_page', 100)],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        [$trainerId, $memberId] = $this->resolvePair($request, (int) $validated['recipient_id']);
        $perPage = (int) ($validated['per_page'] ?? config('chat.messages_per_page', 80));
        $perPage = min(max($perPage, 1), (int) config('chat.max_messages_per_page', 100));
        $beforeId = (int) ($validated['before_id'] ?? 0);

        $rows = ChatMessage::query()
            ->where('trainer_id', $trainerId)
            ->where('member_id', $memberId)
            ->when($beforeId > 0, fn ($query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;
        $messages = $rows->take($perPage)->reverse()->values();
        $oldestMessageId = $messages->first()?->id;

        return $this->successWithMeta(
            ChatMessageResource::collection($messages),
            [
                'cursor' => [
                    'before_id' => $beforeId > 0 ? $beforeId : null,
                    'next_before_id' => $hasMore ? $oldestMessageId : null,
                    'has_more' => $hasMore,
                    'per_page' => $perPage,
                ],
                'retention' => [
                    'days' => (int) config('chat.message_retention_days', 365),
                ],
            ],
            'Chat messages fetched successfully.'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:4000'],
            'client_message_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        [$trainerId, $memberId] = $this->resolvePair($request, (int) $validated['recipient_id']);
        $senderId = $request->user()->id;
        $recipientId = (int) $validated['recipient_id'];

        $created = false;
        $message = DB::transaction(function () use ($trainerId, $memberId, $senderId, $recipientId, $validated, &$created): ChatMessage {
            $clientMessageId = $validated['client_message_id'] ?? null;
            if ($clientMessageId) {
                $existing = ChatMessage::query()
                    ->where('room', $this->room($trainerId, $memberId))
                    ->where('sender_id', $senderId)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $message = ChatMessage::query()->create([
                'room' => $this->room($trainerId, $memberId),
                'trainer_id' => $trainerId,
                'member_id' => $memberId,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'body' => trim($validated['message']),
                'client_message_id' => $validated['client_message_id'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'delivery_status' => 'sent',
                'delivered_at' => now(),
            ]);

            $this->updateConversationForMessage($message);
            $created = true;

            return $message;
        });

        if ($created) {
            $notification = Notification::query()->create([
                'user_id' => $recipientId,
                'type' => NotificationType::TrainerMessage->value,
                'title' => $request->user()->name.' sent you a message',
                'body' => $message->body,
                'data' => [
                    'room' => $message->room,
                    'sender_id' => $senderId,
                    'trainer_id' => $trainerId,
                    'member_id' => $memberId,
                ],
                'created_by_user_id' => $senderId,
            ]);

            $this->sendChatPush($message, $notification);
        }

        return $this->success(ChatMessageResource::make($message), 'Message sent successfully.', 201);
    }

    public function markRead(Request $request)
    {
        [$trainerId, $memberId] = $this->resolvePair($request, (int) $request->integer('recipient_id'));

        ChatMessage::query()
            ->where('trainer_id', $trainerId)
            ->where('member_id', $memberId)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'delivery_status' => 'read']);

        $this->markConversationRead($trainerId, $memberId, $request->user()->id);

        return $this->success(null, 'Chat messages marked read.');
    }

    public function internalStore(Request $request)
    {
        $this->assertInternal($request);
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:120'],
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'member_id' => ['required', 'integer', 'exists:users,id'],
            'sender_id' => ['required', 'integer', 'exists:users,id'],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['required', 'string', 'max:4000'],
            'client_message_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
            'suppress_push' => ['sometimes', 'boolean'],
        ]);

        $created = false;
        $message = DB::transaction(function () use ($validated, &$created): ChatMessage {
            if (! empty($validated['client_message_id'])) {
                $existing = ChatMessage::query()
                    ->where('room', $validated['room'])
                    ->where('sender_id', $validated['sender_id'])
                    ->where('client_message_id', $validated['client_message_id'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $message = ChatMessage::query()->create([
                'room' => $validated['room'],
                'trainer_id' => $validated['trainer_id'],
                'member_id' => $validated['member_id'],
                'sender_id' => $validated['sender_id'],
                'recipient_id' => $validated['recipient_id'],
                'body' => trim($validated['message']),
                'client_message_id' => $validated['client_message_id'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'delivery_status' => 'sent',
                'delivered_at' => now(),
            ]);

            $this->updateConversationForMessage($message);
            $created = true;

            return $message;
        });

        if ($created) {
            $senderName = User::query()->whereKey($message->sender_id)->value('name') ?: 'New message';
            $notification = Notification::query()->create([
                'user_id' => $message->recipient_id,
                'type' => NotificationType::TrainerMessage->value,
                'title' => $senderName.' sent you a message',
                'body' => $message->body,
                'data' => [
                    'room' => $message->room,
                    'sender_id' => $message->sender_id,
                    'trainer_id' => $message->trainer_id,
                    'member_id' => $message->member_id,
                ],
                'created_by_user_id' => $message->sender_id,
            ]);

            if (! ($validated['suppress_push'] ?? false)) {
                $this->sendChatPush($message, $notification);
            }
        }

        return $this->success(ChatMessageResource::make($message), 'Message persisted.', 201);
    }

    public function internalRead(Request $request)
    {
        $this->assertInternal($request);
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:120'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message_ids' => ['nullable', 'array'],
        ]);

        $query = ChatMessage::query()
            ->where('room', $validated['room'])
            ->where('recipient_id', $validated['user_id'])
            ->whereNull('read_at');

        if (! empty($validated['message_ids'])) {
            $query->whereIn('id', $validated['message_ids']);
        }

        $query->update(['read_at' => now(), 'delivery_status' => 'read']);

        [$trainerId, $memberId] = $this->parseRoom($validated['room']);
        $this->markConversationRead($trainerId, $memberId, (int) $validated['user_id']);

        return $this->success(null, 'Read receipt persisted.');
    }

    private function ensureConversation(int $trainerId, int $memberId): ChatConversation
    {
        $room = $this->room($trainerId, $memberId);

        return ChatConversation::query()->firstOrCreate([
            'room' => $room,
        ], [
            'trainer_id' => $trainerId,
            'member_id' => $memberId,
        ]);
    }

    private function ensureTrainerConversations(int $trainerId, array $memberIds): void
    {
        if (empty($memberIds)) {
            return;
        }

        $existingRooms = ChatConversation::query()
            ->where('trainer_id', $trainerId)
            ->whereIn('member_id', $memberIds)
            ->pluck('room')
            ->all();

        $now = now();
        $rows = collect($memberIds)
            ->map(fn (int $memberId): array => [
                'room' => $this->room($trainerId, $memberId),
                'trainer_id' => $trainerId,
                'member_id' => $memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->reject(fn (array $row): bool => in_array($row['room'], $existingRooms, true))
            ->values()
            ->all();

        if (! empty($rows)) {
            ChatConversation::query()->insert($rows);
        }
    }

    private function updateConversationForMessage(ChatMessage $message): void
    {
        $conversation = $this->ensureConversation($message->trainer_id, $message->member_id);
        $trainerUnreadCount = $conversation->trainer_unread_count ?? 0;
        $memberUnreadCount = $conversation->member_unread_count ?? 0;

        if ($message->recipient_id === $message->trainer_id) {
            $trainerUnreadCount++;
        }

        if ($message->recipient_id === $message->member_id) {
            $memberUnreadCount++;
        }

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_body' => $message->body,
            'last_sender_id' => $message->sender_id,
            'last_message_at' => $message->created_at ?? now(),
            'trainer_unread_count' => $trainerUnreadCount,
            'member_unread_count' => $memberUnreadCount,
        ])->save();
    }

    private function markConversationRead(int $trainerId, int $memberId, int $viewerId): void
    {
        $conversation = $this->ensureConversation($trainerId, $memberId);

        if ($viewerId === $trainerId) {
            $conversation->forceFill([
                'trainer_unread_count' => 0,
                'trainer_read_at' => now(),
            ])->save();

            return;
        }

        if ($viewerId === $memberId) {
            $conversation->forceFill([
                'member_unread_count' => 0,
                'member_read_at' => now(),
            ])->save();
        }
    }

    private function sendChatPush(ChatMessage $message, Notification $notification): void
    {
        $recipient = User::query()->find($message->recipient_id);
        if (! $recipient) {
            return;
        }

        $appRole = $message->recipient_id === $message->trainer_id
            ? RoleName::Trainer->value
            : RoleName::Member->value;

        $this->fcmNotificationService->sendToUser(
            user: $recipient,
            title: $notification->title,
            body: $notification->body,
            data: [
                'type' => 'chat_message',
                'notification_id' => $notification->id,
                'room' => $message->room,
                'sender_id' => $message->sender_id,
                'recipient_id' => $message->recipient_id,
                'trainer_id' => $message->trainer_id,
                'member_id' => $message->member_id,
                'message_id' => $message->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            appRole: $appRole,
        );
    }

    private function parseRoom(string $room): array
    {
        if (! preg_match('/^trainer:(\d+):member:(\d+)$/', $room, $matches)) {
            abort(422, 'Invalid chat room.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function resolvePair(Request $request, int $recipientId): array
    {
        $user = $request->user();

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = TrainerProfile::query()->where('user_id', $user->id)->firstOrFail();
            $memberProfile = MemberProfile::query()
                ->where('user_id', $recipientId)
                ->where('assigned_trainer_user_id', $user->id)
                ->where('gym_id', $trainerProfile->gym_id)
                ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id))
                ->first();

            if (! $memberProfile) {
                throw ValidationException::withMessages(['recipient_id' => ['Trainer can chat only with assigned members.']]);
            }

            return [$user->id, $recipientId];
        }

        if ($user->active_role === RoleName::Member->value) {
            $memberProfile = MemberProfile::query()
                ->where('user_id', $user->id)
                ->where('assigned_trainer_user_id', $recipientId)
                ->first();

            if (! $memberProfile) {
                throw ValidationException::withMessages(['recipient_id' => ['Member can chat only with the assigned trainer.']]);
            }

            return [$recipientId, $user->id];
        }

        abort(403, 'This role cannot access trainer-member chat.');
    }

    private function room(int $trainerId, int $memberId): string
    {
        return 'trainer:'.$trainerId.':member:'.$memberId;
    }

    private function assertInternal(Request $request): void
    {
        abort_unless(
            $request->header('X-Internal-Api-Key') === config('services.realtime.internal_api_key', env('SOCKET_INTERNAL_API_KEY', 'change-me')),
            401
        );
    }
}
