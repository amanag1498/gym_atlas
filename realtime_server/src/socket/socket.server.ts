import type { Server, Socket } from 'socket.io';
import { ChatAuthorizationService } from '../services/chat-authorization.service';
import { ChatPersistenceService } from '../services/chat-persistence.service';
import { logger } from '../services/logger';
import { PresenceService } from '../services/presence.service';
import { RoomService } from '../services/room.service';
import type {
  AuthenticatedSocketData,
  ChatReadPayload,
  ChatSendPayload,
  PresenceUpdatePayload,
} from '../types/socket';
import { rooms } from './rooms';

type TypedSocket = Socket<Record<string, never>, Record<string, never>, Record<string, never>, AuthenticatedSocketData>;

const chatAuthorizationService = new ChatAuthorizationService();
const chatPersistenceService = new ChatPersistenceService();
const presenceService = new PresenceService();
const roomService = new RoomService();
const maxMessagesPerWindow = 20;
const messageWindowMs = 10_000;

function assertSocketUser(socket: TypedSocket) {
  const user = socket.data.user;
  if (!user) {
    throw new Error('Unauthenticated socket.');
  }

  return user;
}

function assertChatSendPayload(payload: ChatSendPayload): void {
  if (!payload || !Number.isSafeInteger(payload.recipientId) || payload.recipientId <= 0) {
    throw new Error('A valid recipient is required.');
  }

  if (typeof payload.message !== 'string' || payload.message.trim().length === 0 || payload.message.length > 4000) {
    throw new Error('Message must contain between 1 and 4000 characters.');
  }

  if (payload.clientMessageId !== undefined
    && (typeof payload.clientMessageId !== 'string'
      || payload.clientMessageId.length === 0
      || payload.clientMessageId.length > 120)) {
    throw new Error('Invalid client message identifier.');
  }

  if (payload.metadata !== undefined) {
    if (!payload.metadata
      || typeof payload.metadata !== 'object'
      || Array.isArray(payload.metadata)
      || JSON.stringify(payload.metadata).length > 8192) {
      throw new Error('Invalid or oversized chat metadata.');
    }
  }
}

function assertChatReadPayload(payload: ChatReadPayload): void {
  if (!payload || !Number.isSafeInteger(payload.recipientId) || payload.recipientId <= 0) {
    throw new Error('A valid recipient is required.');
  }

  if (!Array.isArray(payload.messageIds) || payload.messageIds.length > 1000) {
    throw new Error('Invalid read receipt message list.');
  }

  if (payload.messageIds.some((id) => typeof id !== 'string' || !/^\d+$/.test(id))) {
    throw new Error('Read receipt message identifiers must be positive integers.');
  }
}

export function registerSocketServer(io: Server): void {
  io.on('connection', async (socket: TypedSocket) => {
    const user = assertSocketUser(socket);
    const sentMessageTimes: number[] = [];
    await roomService.joinBaseRooms(socket, user);
    presenceService.registerConnection(io, user, socket.id);

    logger.info('Socket connected', {
      socketId: socket.id,
      userId: user.id,
      role: user.activeRole,
    });

    socket.on('chat:send', async (payload: ChatSendPayload, acknowledgement?: (response: unknown) => void) => {
      try {
        assertChatSendPayload(payload);
        const now = Date.now();
        while ((sentMessageTimes[0] ?? now) < now - messageWindowMs) {
          sentMessageTimes.shift();
        }
        if (sentMessageTimes.length >= maxMessagesPerWindow) {
          throw new Error('Message rate limit exceeded. Please wait before sending again.');
        }
        sentMessageTimes.push(now);

        const actor = assertSocketUser(socket);
        const authorizedPeer = chatAuthorizationService.authorizePeer(actor, payload.recipientId);
        await socket.join(authorizedPeer.room);
        const persisted = await chatPersistenceService.persistMessage(
          authorizedPeer.room,
          actor.id,
          payload,
        );

        const chatMessageEvent = {
          room: authorizedPeer.room,
          trainerId: authorizedPeer.trainerId,
          memberId: authorizedPeer.memberId,
          message: persisted,
        };

        io.to(authorizedPeer.room)
          .to(rooms.user(payload.recipientId))
          .emit('chat:new_message', chatMessageEvent);

        acknowledgement?.({
          ok: true,
          room: authorizedPeer.room,
          message: persisted,
        });
      } catch (error) {
        acknowledgement?.({
          ok: false,
          error: error instanceof Error ? error.message : 'Unable to send chat message.',
        });
      }
    });

    socket.on('chat:read', async (payload: ChatReadPayload, acknowledgement?: (response: unknown) => void) => {
      try {
        assertChatReadPayload(payload);
        const actor = assertSocketUser(socket);
        const authorizedPeer = chatAuthorizationService.authorizePeer(actor, payload.recipientId);
        await socket.join(authorizedPeer.room);

        const receipt = await chatPersistenceService.persistReadReceipt(authorizedPeer.room, actor.id, payload);

        io.to(authorizedPeer.room).emit('chat:read_receipt', {
          room: authorizedPeer.room,
          userId: actor.id,
          recipientId: payload.recipientId,
          messageIds: receipt.messageIds,
          readAt: receipt.readAt,
        });

        acknowledgement?.({
          ok: true,
          receipt,
        });
      } catch (error) {
        acknowledgement?.({
          ok: false,
          error: error instanceof Error ? error.message : 'Unable to mark messages as read.',
        });
      }
    });

    socket.on('presence:update', (payload: PresenceUpdatePayload) => {
      const actor = assertSocketUser(socket);
      const status = payload?.status ?? 'online';
      if (!['online', 'offline', 'away'].includes(status)) {
        return;
      }
      presenceService.updateStatus(io, actor.id, status);
    });

    socket.on('disconnect', () => {
      presenceService.unregisterConnection(io, user, socket.id);
      logger.info('Socket disconnected', {
        socketId: socket.id,
        userId: user.id,
      });
    });
  });
}
