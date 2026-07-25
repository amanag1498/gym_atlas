import { Router } from 'express';
import type { Server } from 'socket.io';
import { rooms } from '../socket/rooms';
import type {
  InternalAnnouncementPayload,
  InternalChatMessagePayload,
  InternalChatReadPayload,
  InternalNotificationPayload,
} from '../types/socket';

export function buildInternalRoutes(io: Server): Router {
  const router = Router();

  router.post('/notifications', (request, response) => {
    const payload = request.body as InternalNotificationPayload;

    io.to(rooms.user(payload.userId))
      .to(rooms.userNotifications(payload.userId))
      .emit('notification:new', {
      title: payload.title,
      body: payload.body,
      type: payload.type,
      gymId: payload.gymId ?? null,
      branchId: payload.branchId ?? null,
      data: payload.data ?? {},
      createdAt: new Date().toISOString(),
    });

    response.json({
      success: true,
      message: 'Notification event published.',
    });
  });

  router.post('/announcements', (request, response) => {
    const payload = request.body as InternalAnnouncementPayload;
    const event = {
      title: payload.title,
      message: payload.message,
      audience: payload.audience,
      gymId: payload.gymId ?? null,
      branchId: payload.branchId ?? null,
      data: payload.data ?? {},
      createdAt: new Date().toISOString(),
    };

    if (payload.audience === 'platform') {
      io.to(rooms.platformAnnouncements()).emit('announcement:new', event);
    }

    if (payload.audience === 'gym' && payload.gymId) {
      io.to(rooms.gymAnnouncements(payload.gymId)).emit('announcement:new', event);
    }

    if (payload.audience === 'branch' && payload.gymId && payload.branchId) {
      io.to(rooms.branchAnnouncements(payload.gymId, payload.branchId)).emit('announcement:new', event);
    }

    response.json({
      success: true,
      message: 'Announcement event published.',
    });
  });

  router.post('/chat/messages', (request, response) => {
    const payload = request.body as InternalChatMessagePayload | null;
    if (!payload
      || !Number.isSafeInteger(payload.trainerId)
      || !Number.isSafeInteger(payload.memberId)
      || typeof payload.room !== 'string') {
      response.status(422).json({
        success: false,
        message: 'Invalid chat message event.',
      });
      return;
    }

    const expectedRoom = rooms.trainerMemberChat(payload.trainerId, payload.memberId);
    const participants = [payload.trainerId, payload.memberId];

    if (!payload.message
      || payload.room !== expectedRoom
      || payload.message.room !== expectedRoom
      || typeof payload.message.id !== 'string'
      || typeof payload.message.body !== 'string'
      || typeof payload.message.createdAt !== 'string'
      || !participants.includes(payload.message.senderId)
      || !participants.includes(payload.message.recipientId)
      || payload.message.senderId === payload.message.recipientId) {
      response.status(422).json({
        success: false,
        message: 'Invalid chat message event.',
      });
      return;
    }

    io.to(expectedRoom)
      .to(rooms.user(payload.message.recipientId))
      .emit('chat:new_message', payload);

    response.json({
      success: true,
      message: 'Chat message event published.',
    });
  });

  router.post('/chat/read', (request, response) => {
    const payload = request.body as InternalChatReadPayload | null;
    if (!payload || typeof payload.room !== 'string') {
      response.status(422).json({
        success: false,
        message: 'Invalid chat read event.',
      });
      return;
    }

    const match = /^trainer:(\d+):member:(\d+)$/.exec(payload.room);

    if (!match
      || !Number.isSafeInteger(payload.userId)
      || !Number.isSafeInteger(payload.recipientId)
      || !Array.isArray(payload.messageIds)
      || payload.messageIds.length > 1000
      || payload.messageIds.some((id) => typeof id !== 'string' || !/^\d+$/.test(id))
      || typeof payload.readAt !== 'string') {
      response.status(422).json({
        success: false,
        message: 'Invalid chat read event.',
      });
      return;
    }

    const participants = [Number(match[1]), Number(match[2])];
    if (!participants.includes(payload.userId)
      || !participants.includes(payload.recipientId)
      || payload.userId === payload.recipientId) {
      response.status(422).json({
        success: false,
        message: 'Invalid chat read participants.',
      });
      return;
    }

    io.to(payload.room).emit('chat:read_receipt', payload);

    response.json({
      success: true,
      message: 'Chat read event published.',
    });
  });

  return router;
}
