<?php

namespace App\Http\Controllers\Api\Chat;

use App\Enums\NotificationType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSafetyAction;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Member\MemberAppService;
use App\Services\Members\GymMemberAccessService;
use App\Services\Notification\NotificationService;
use App\Services\Realtime\RealtimePublisher;
use App\Services\Trainer\IndependentCoachingAccessService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainerMemberChatController extends Controller
{
    public function __construct(
        private readonly FcmNotificationService $fcmNotificationService,
        private readonly NotificationService $notificationService,
        private readonly MemberAppService $memberAppService,
        private readonly RealtimePublisher $realtimePublisher,
        private readonly IndependentCoachingAccessService $independentCoachingAccessService,
        private readonly GymMemberAccessService $gymMemberAccessService,
    ) {}

    public function conversations(Request $request)
    {
        $user = $request->user();

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = $this->activeTrainerProfileForChat($user);
            $profileQuery = MemberProfile::query()
                ->with('user')
                ->where('assigned_trainer_user_id', $user->id)
                ->where('gym_id', $trainerProfile->gym_id)
                ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id));
            $gymMemberIds = $this->gymMemberAccessService
                ->scopeAccessibleProfiles($profileQuery)
                ->pluck('user_id');

            $independentMemberIds = collect();
            if (
                $trainerProfile->gym_id === null
                && $trainerProfile->verification_status === 'verified'
            ) {
                $independentMemberIds = $this->independentCoachingAccessService
                    ->activeMemberIdsForTrainer($user, 'chat');
            }
            $memberIds = $gymMemberIds
                ->merge($independentMemberIds)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $this->ensureTrainerConversations($user->id, $memberIds);

            $paginator = ChatConversation::query()
                ->with(['trainer', 'member', 'lastMessage'])
                ->where('trainer_id', $user->id)
                ->whereIn('member_id', $memberIds)
                ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at)'))
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 50));

            return $this->paginated($paginator, ChatConversationResource::collection($paginator->getCollection()), 'Chat conversations fetched successfully.');
        }

        if ($user->active_role === RoleName::Member->value) {
            $profile = $this->memberAppService->memberProfileForChat($user);

            $trainerIds = collect([$profile?->assigned_trainer_user_id])
                ->merge($this->independentCoachingAccessService->activeTrainerIdsForMember($user, 'chat'))
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($trainerIds->isEmpty()) {
                $paginator = ChatConversation::query()
                    ->whereRaw('1 = 0')
                    ->paginate($request->integer('per_page', 50));

                return $this->paginated($paginator, ChatConversationResource::collection($paginator->getCollection()), 'No assigned trainer conversation found.');
            }

            $this->ensureMemberConversations($user->id, $trainerIds->all());
            $paginator = ChatConversation::query()
                ->with(['trainer', 'member', 'lastMessage'])
                ->where('member_id', $user->id)
                ->whereIn('trainer_id', $trainerIds)
                ->orderByDesc(DB::raw('COALESCE(last_message_at, updated_at)'))
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 50));

            return $this->paginated($paginator, ChatConversationResource::collection($paginator->getCollection()), 'Chat conversations fetched successfully.');
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
        $this->assertChatSafetyAllowsSend($request->user(), $recipientId);

        $created = false;
        $message = DB::transaction(function () use ($trainerId, $memberId, $senderId, $recipientId, $validated, &$created): ChatMessage {
            $clientMessageId = $validated['client_message_id'] ?? null;
            if ($clientMessageId) {
                $message = ChatMessage::query()->firstOrCreate([
                    'room' => $this->room($trainerId, $memberId),
                    'sender_id' => $senderId,
                    'client_message_id' => $clientMessageId,
                ], [
                    'trainer_id' => $trainerId,
                    'member_id' => $memberId,
                    'recipient_id' => $recipientId,
                    'body' => trim($validated['message']),
                    'metadata' => $validated['metadata'] ?? null,
                    'delivery_status' => 'sent',
                    'delivered_at' => now(),
                ]);

                if (! $message->wasRecentlyCreated) {
                    return $message;
                }
            } else {
                $message = ChatMessage::query()->create([
                    'room' => $this->room($trainerId, $memberId),
                    'trainer_id' => $trainerId,
                    'member_id' => $memberId,
                    'sender_id' => $senderId,
                    'recipient_id' => $recipientId,
                    'body' => trim($validated['message']),
                    'metadata' => $validated['metadata'] ?? null,
                    'delivery_status' => 'sent',
                    'delivered_at' => now(),
                ]);
            }

            $this->updateConversationForMessage($message);
            $created = true;

            return $message;
        });

        if ($created) {
            $this->realtimePublisher->chatMessage($message);
            if (! $this->realtimePublisher->isUserActiveInChat(
                $message->room,
                $message->recipient_id,
            )) {
                $this->sendChatPush($message, $request->user()->name);
            }
        }

        return $this->success(ChatMessageResource::make($message), 'Message sent successfully.', 201);
    }

    public function safetyStatus(Request $request)
    {
        $validated = $request->validate([
            'other_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $otherUserId = (int) $validated['other_user_id'];
        $this->resolvePair($request, $otherUserId);
        $userId = (int) $request->user()->id;

        return $this->success([
            'terms_accepted' => $request->user()->accepted_chat_terms_at !== null,
            'blocked_by_me' => $this->activeBlockExists($userId, $otherUserId),
            'blocked_me' => $this->activeBlockExists($otherUserId, $userId),
        ], 'Chat safety status fetched successfully.');
    }

    public function acceptSafetyTerms(Request $request)
    {
        $request->user()->forceFill(['accepted_chat_terms_at' => now()])->save();

        return $this->success([
            'terms_accepted' => true,
        ], 'Chat terms accepted successfully.');
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'reported_user_id' => ['required', 'integer', 'exists:users,id'],
            'message_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
            'reason' => ['required', 'string', 'in:harassment,inappropriate_content,spam,safety_concern,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $targetId = (int) $validated['reported_user_id'];
        [$trainerId, $memberId] = $this->resolvePair($request, $targetId);

        if (isset($validated['message_id'])) {
            ChatMessage::query()
                ->whereKey((int) $validated['message_id'])
                ->where('trainer_id', $trainerId)
                ->where('member_id', $memberId)
                ->firstOrFail();
        }

        $report = ChatSafetyAction::query()->create([
            'actor_id' => $request->user()->id,
            'target_id' => $targetId,
            'type' => 'report',
            'chat_message_id' => $validated['message_id'] ?? null,
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ]);

        return $this->success(['report_id' => $report->id], 'Report submitted successfully.', 201);
    }

    public function block(Request $request)
    {
        $validated = $request->validate([
            'blocked_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $targetId = (int) $validated['blocked_user_id'];
        $this->resolvePair($request, $targetId);

        ChatSafetyAction::query()->updateOrCreate([
            'actor_id' => $request->user()->id,
            'target_id' => $targetId,
            'type' => 'block',
        ], [
            'resolved_at' => null,
        ]);

        return $this->success(['blocked' => true], 'User blocked successfully.');
    }

    public function unblock(Request $request, User $user)
    {
        $this->resolvePair($request, (int) $user->id);
        ChatSafetyAction::query()
            ->where('actor_id', $request->user()->id)
            ->where('target_id', $user->id)
            ->where('type', 'block')
            ->update(['resolved_at' => now()]);

        return $this->success(['blocked' => false], 'User unblocked successfully.');
    }

    public function markRead(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        [$trainerId, $memberId] = $this->resolvePair($request, (int) $validated['recipient_id']);
        $recipientId = (int) $validated['recipient_id'];

        $receipt = DB::transaction(function () use ($trainerId, $memberId, $recipientId, $request): array {
            $this->lockConversation($trainerId, $memberId);
            $readAt = now();
            $messageIds = ChatMessage::query()
                ->where('trainer_id', $trainerId)
                ->where('member_id', $memberId)
                ->where('recipient_id', $request->user()->id)
                ->whereNull('read_at')
                ->pluck('id')
                ->all();

            if ($messageIds !== []) {
                ChatMessage::query()
                    ->whereIn('id', $messageIds)
                    ->update(['read_at' => $readAt, 'delivery_status' => 'read']);
            }

            $this->markConversationRead($trainerId, $memberId, $request->user()->id, $readAt);

            return [
                'room' => $this->room($trainerId, $memberId),
                'user_id' => $request->user()->id,
                'recipient_id' => $recipientId,
                'message_ids' => $messageIds,
                'read_at' => $readAt->toIso8601String(),
            ];
        });

        $this->realtimePublisher->chatRead(
            $receipt['room'],
            $receipt['user_id'],
            $receipt['recipient_id'],
            $receipt['message_ids'],
            $receipt['read_at'],
        );

        return $this->success($receipt, 'Chat messages marked read.');
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
            'recipient_active_in_chat' => ['sometimes', 'boolean'],
        ]);

        $canonicalRoom = $this->room((int) $validated['trainer_id'], (int) $validated['member_id']);
        abort_unless($this->canonicalRoom($validated['room']) === $canonicalRoom, 422, 'Chat room does not match its participants.');
        $this->assertAssignedPair((int) $validated['trainer_id'], (int) $validated['member_id']);
        $this->assertMessageParticipants(
            (int) $validated['trainer_id'],
            (int) $validated['member_id'],
            (int) $validated['sender_id'],
            (int) $validated['recipient_id'],
        );
        $sender = User::query()->findOrFail((int) $validated['sender_id']);
        $this->assertChatSafetyAllowsSend($sender, (int) $validated['recipient_id']);
        $validated['room'] = $canonicalRoom;

        $created = false;
        $message = DB::transaction(function () use ($validated, &$created): ChatMessage {
            if (! empty($validated['client_message_id'])) {
                $message = ChatMessage::query()->firstOrCreate([
                    'room' => $validated['room'],
                    'sender_id' => $validated['sender_id'],
                    'client_message_id' => $validated['client_message_id'],
                ], [
                    'trainer_id' => $validated['trainer_id'],
                    'member_id' => $validated['member_id'],
                    'recipient_id' => $validated['recipient_id'],
                    'body' => trim($validated['message']),
                    'metadata' => $validated['metadata'] ?? null,
                    'delivery_status' => 'sent',
                    'delivered_at' => now(),
                ]);

                if (! $message->wasRecentlyCreated) {
                    return $message;
                }
            } else {
                $message = ChatMessage::query()->create([
                    'room' => $validated['room'],
                    'trainer_id' => $validated['trainer_id'],
                    'member_id' => $validated['member_id'],
                    'sender_id' => $validated['sender_id'],
                    'recipient_id' => $validated['recipient_id'],
                    'body' => trim($validated['message']),
                    'metadata' => $validated['metadata'] ?? null,
                    'delivery_status' => 'sent',
                    'delivered_at' => now(),
                ]);
            }

            $this->updateConversationForMessage($message);
            $created = true;

            return $message;
        });

        if ($created && ! ($validated['recipient_active_in_chat'] ?? false)) {
            $senderName = User::query()->whereKey($message->sender_id)->value('name') ?: 'New message';
            $this->sendChatPush($message, $senderName);
        }

        return $this->success(ChatMessageResource::make($message), 'Message persisted.', 201);
    }

    public function internalHealth(Request $request)
    {
        $this->assertInternal($request);

        return $this->success(['service' => 'chat-persistence'], 'Realtime chat persistence is ready.');
    }

    public function internalRead(Request $request)
    {
        $this->assertInternal($request);
        $validated = $request->validate([
            'room' => ['required', 'string', 'max:120'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message_ids' => ['nullable', 'array', 'max:1000'],
            'message_ids.*' => ['integer', 'min:1'],
        ]);

        [$trainerId, $memberId] = $this->parseRoom($validated['room']);
        $this->assertAssignedPair($trainerId, $memberId);
        abort_unless(in_array((int) $validated['user_id'], [$trainerId, $memberId], true), 422, 'Read receipt user is not a room participant.');

        $receipt = DB::transaction(function () use ($validated, $trainerId, $memberId): array {
            $this->lockConversation($trainerId, $memberId);
            $readAt = now();
            $query = ChatMessage::query()
                ->where('room', $this->room($trainerId, $memberId))
                ->where('recipient_id', $validated['user_id'])
                ->whereNull('read_at');

            if (! empty($validated['message_ids'])) {
                $query->whereIn('id', $validated['message_ids']);
            }

            $messageIds = (clone $query)->pluck('id')->all();
            if ($messageIds !== []) {
                ChatMessage::query()
                    ->whereIn('id', $messageIds)
                    ->update(['read_at' => $readAt, 'delivery_status' => 'read']);
            }
            $this->markConversationRead($trainerId, $memberId, (int) $validated['user_id'], $readAt);

            return [
                'room' => $this->room($trainerId, $memberId),
                'user_id' => (int) $validated['user_id'],
                'message_ids' => $messageIds,
                'read_at' => $readAt->toIso8601String(),
            ];
        });

        return $this->success($receipt, 'Read receipt persisted.');
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

        $now = now();
        $rows = collect($memberIds)
            ->map(fn (int $memberId): array => [
                'room' => $this->room($trainerId, $memberId),
                'trainer_id' => $trainerId,
                'member_id' => $memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        ChatConversation::query()->insertOrIgnore($rows);
    }

    private function ensureMemberConversations(int $memberId, array $trainerIds): void
    {
        foreach ($trainerIds as $trainerId) {
            $this->ensureConversation((int) $trainerId, $memberId);
        }
    }

    private function updateConversationForMessage(ChatMessage $message): void
    {
        $conversation = $this->lockConversation($message->trainer_id, $message->member_id);
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

    private function markConversationRead(
        int $trainerId,
        int $memberId,
        int $viewerId,
        CarbonInterface $readAt,
    ): void {
        $conversation = $this->ensureConversation($trainerId, $memberId);
        $unreadCount = ChatMessage::query()
            ->where('trainer_id', $trainerId)
            ->where('member_id', $memberId)
            ->where('recipient_id', $viewerId)
            ->whereNull('read_at')
            ->count();

        if ($viewerId === $trainerId) {
            $conversation->forceFill([
                'trainer_unread_count' => $unreadCount,
                'trainer_read_at' => $readAt,
            ])->save();

            return;
        }

        if ($viewerId === $memberId) {
            $conversation->forceFill([
                'member_unread_count' => $unreadCount,
                'member_read_at' => $readAt,
            ])->save();
        }
    }

    private function sendChatPush(ChatMessage $message, string $senderName): void
    {
        $recipient = User::query()->find($message->recipient_id);
        if (! $recipient) {
            return;
        }

        $member = User::query()->find($message->member_id);
        $scope = $member
            ? $this->memberAppService->gymProfileForTrainer($member, (int) $message->trainer_id)
            : null;
        $trainer = User::query()->find($message->trainer_id);
        $isIndependentRelationship = $trainer && $member
            ? $this->independentCoachingAccessService->hasActiveRelationship($trainer, $member, 'chat')
            : false;
        if (! $this->notificationService->isEnabled(
            $recipient->id,
            NotificationType::TrainerMessage->value,
            $isIndependentRelationship ? null : $scope?->gym_id,
            $isIndependentRelationship ? null : $scope?->branch_id,
        )) {
            return;
        }

        $appRole = $message->recipient_id === $message->trainer_id
            ? RoleName::Trainer->value
            : RoleName::Member->value;

        $this->fcmNotificationService->sendToUser(
            user: $recipient,
            title: $senderName.' sent you a message',
            body: $message->body,
            data: [
                'type' => 'chat_message',
                'room' => $message->room,
                'sender_id' => $message->sender_id,
                'recipient_id' => $message->recipient_id,
                'trainer_id' => $message->trainer_id,
                'member_id' => $message->member_id,
                'message_id' => $message->id,
                'coaching_scope' => $isIndependentRelationship ? 'independent' : 'gym',
                'gym_id' => $isIndependentRelationship ? null : $scope?->gym_id,
                'branch_id' => $isIndependentRelationship ? null : $scope?->branch_id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
            appRole: $appRole,
        );
    }

    private function parseRoom(string $room): array
    {
        if (! preg_match('/^(?:chat:)?trainer:(\d+):member:(\d+)$/', $room, $matches)) {
            abort(422, 'Invalid chat room.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function resolvePair(Request $request, int $recipientId): array
    {
        $user = $request->user();

        if ($user->active_role === RoleName::Trainer->value) {
            $trainerProfile = $this->activeTrainerProfileForChat($user);
            $memberProfileQuery = MemberProfile::query()
                ->where('user_id', $recipientId)
                ->where('assigned_trainer_user_id', $user->id)
                ->where('gym_id', $trainerProfile->gym_id)
                ->when($trainerProfile->branch_id, fn ($query) => $query->where('branch_id', $trainerProfile->branch_id));
            $memberProfile = $this->gymMemberAccessService
                ->scopeAccessibleProfiles($memberProfileQuery)
                ->first();

            if (! $memberProfile && ! $this->independentCoachingAccessService->hasActiveRelationship($user, User::query()->findOrFail($recipientId), 'chat')) {
                throw ValidationException::withMessages(['recipient_id' => ['Trainer can chat only with assigned members.']]);
            }

            return [$user->id, $recipientId];
        }

        if ($user->active_role === RoleName::Member->value) {
            $gymAssignment = $this->memberAppService->gymProfileForTrainer($user, $recipientId) !== null;
            $independentAssignment = $this->independentCoachingAccessService
                ->activeTrainerIdsForMember($user, 'chat')
                ->contains($recipientId);

            if (! $gymAssignment && ! $independentAssignment) {
                throw ValidationException::withMessages(['recipient_id' => ['Member can chat only with the assigned trainer.']]);
            }

            return [$recipientId, $user->id];
        }

        abort(403, 'This role cannot access trainer-member chat.');
    }

    private function assertChatSafetyAllowsSend(User $sender, int $recipientId): void
    {
        abort_if(
            $sender->accepted_chat_terms_at === null,
            403,
            'Accept the chat terms before sending messages.'
        );
        abort_if(
            $this->activeBlockExists((int) $sender->id, $recipientId)
                || $this->activeBlockExists($recipientId, (int) $sender->id),
            403,
            'Messaging is unavailable because this conversation is blocked.'
        );
    }

    private function activeBlockExists(int $actorId, int $targetId): bool
    {
        return ChatSafetyAction::query()
            ->where('actor_id', $actorId)
            ->where('target_id', $targetId)
            ->where('type', 'block')
            ->whereNull('resolved_at')
            ->exists();
    }

    private function room(int $trainerId, int $memberId): string
    {
        return 'trainer:'.$trainerId.':member:'.$memberId;
    }

    private function canonicalRoom(string $room): string
    {
        [$trainerId, $memberId] = $this->parseRoom($room);

        return $this->room($trainerId, $memberId);
    }

    private function lockConversation(int $trainerId, int $memberId): ChatConversation
    {
        $conversation = $this->ensureConversation($trainerId, $memberId);

        return ChatConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
    }

    private function assertAssignedPair(int $trainerId, int $memberId): void
    {
        $member = User::query()->find($memberId);
        abort_unless($member, 422, 'Member account is not available.');
        $memberProfile = $this->memberAppService->gymProfileForTrainer($member, $trainerId);
        $gymAssignment = $memberProfile && (int) $memberProfile->assigned_trainer_user_id === $trainerId;
        $trainer = User::query()->find($trainerId);
        $independentAssignment = $trainer
            ? $this->independentCoachingAccessService->hasActiveRelationship($trainer, $member, 'chat')
            : false;
        abort_unless($gymAssignment || $independentAssignment, 422, 'Trainer-member assignment is no longer active.');

        $trainerProfile = TrainerProfile::query()
            ->where('user_id', $trainerId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereHas('user', fn ($user) => $user->where('is_active', true))
            ->where(function ($scope): void {
                $scope->whereNull('gym_id')
                    ->orWhereHas('gym', fn ($gym) => $gym
                        ->where('is_active', true)
                        ->where('status', 'active')
                        ->where('operational_access_enabled', true));
            })
            ->first();
        abort_unless($trainerProfile, 422, 'Trainer profile is not available.');

        if (! $independentAssignment) {
            abort_unless(
                $memberProfile
                    && (int) $memberProfile->gym_id === (int) $trainerProfile->gym_id
                    && ($trainerProfile->branch_id === null || (int) $memberProfile->branch_id === (int) $trainerProfile->branch_id),
                422,
                'Trainer-member assignment is outside the trainer scope.'
            );
        }
    }

    private function activeTrainerProfileForChat(User $trainer): TrainerProfile
    {
        $profile = TrainerProfile::query()
            ->where('user_id', $trainer->id)
            ->where('is_active', true)
            ->where('status', 'active')
            ->where(function ($scope): void {
                $scope->whereNull('gym_id')
                    ->orWhereHas('gym', fn ($gym) => $gym
                        ->where('is_active', true)
                        ->where('status', 'active')
                        ->where('operational_access_enabled', true));
            })
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'trainer' => ['Trainer chat is unavailable while the trainer or gym is inactive.'],
            ]);
        }

        return $profile;
    }

    private function assertMessageParticipants(int $trainerId, int $memberId, int $senderId, int $recipientId): void
    {
        $participants = [$trainerId, $memberId];

        abort_unless(
            $senderId !== $recipientId
                && in_array($senderId, $participants, true)
                && in_array($recipientId, $participants, true),
            422,
            'Message sender and recipient must be opposite room participants.'
        );
    }

    private function assertInternal(Request $request): void
    {
        $configuredKey = (string) config('services.realtime.internal_api_key');

        abort_unless(
            $configuredKey !== ''
                && (app()->environment('testing') || ($configuredKey !== 'change-me' && strlen($configuredKey) >= 32))
                && is_string($request->header('X-Internal-Api-Key'))
                && hash_equals($configuredKey, (string) $request->header('X-Internal-Api-Key')),
            401
        );
    }
}
