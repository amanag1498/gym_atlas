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
  ChatTypingPayload,
  PresenceUpdatePayload,
} from '../types/socket';
import { rooms } from './rooms';

type TypedSocket = Socket<Record<string, never>, Record<string, never>, Record<string, never>, AuthenticatedSocketData>;

const chatAuthorizationService = new ChatAuthorizationService();
const chatPersistenceService = new ChatPersistenceService();
const presenceService = new PresenceService();
const roomService = new RoomService();

function assertSocketUser(socket: TypedSocket) {
  const user = socket.data.user;
  if (!user) {
    throw new Error('Unauthenticated socket.');
  }

  return user;
}

export function registerSocketServer(io: Server): void {
  io.on('connection', async (socket: TypedSocket) => {
    const user = assertSocketUser(socket);
    await roomService.joinBaseRooms(socket, user);
    presenceService.registerConnection(io, user, socket.id);

    logger.info('Socket connected', {
      socketId: socket.id,
      userId: user.id,
      role: user.activeRole,
    });

    socket.on('chat:send', async (payload: ChatSendPayload, acknowledgement?: (response: unknown) => void) => {
      try {
        const actor = assertSocketUser(socket);
        const authorizedPeer = chatAuthorizationService.authorizePeer(actor, payload.recipientId);
        const suppressPush = presenceService.isOnline(payload.recipientId);

        await socket.join(authorizedPeer.room);
        const persisted = await chatPersistenceService.persistMessage(
          authorizedPeer.room,
          actor.id,
          payload,
          { suppressPush },
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

        io.to(rooms.user(payload.recipientId)).emit('notification:new', {
          type: 'chat_message',
          title: 'New message',
          body: payload.message,
          data: {
            room: authorizedPeer.room,
            senderId: actor.id,
          },
        });

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

    socket.on('chat:typing', async (payload: ChatTypingPayload) => {
      try {
        const actor = assertSocketUser(socket);
        const authorizedPeer = chatAuthorizationService.authorizePeer(actor, payload.recipientId);
        await socket.join(authorizedPeer.room);

        socket.to(authorizedPeer.room).emit('chat:typing', {
          room: authorizedPeer.room,
          userId: actor.id,
          recipientId: payload.recipientId,
          isTyping: payload.isTyping,
        });
      } catch (error) {
        logger.warn('chat:typing rejected', {
          socketId: socket.id,
          error: error instanceof Error ? error.message : 'Unknown error',
        });
      }
    });

    socket.on('chat:read', async (payload: ChatReadPayload, acknowledgement?: (response: unknown) => void) => {
      try {
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
      presenceService.updateStatus(io, actor.id, payload.status ?? 'online');
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
