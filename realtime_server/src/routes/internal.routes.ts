import { Router } from 'express';
import type { Server } from 'socket.io';
import { rooms } from '../socket/rooms';
import type { InternalAnnouncementPayload, InternalNotificationPayload } from '../types/socket';

export function buildInternalRoutes(io: Server): Router {
  const router = Router();

  router.post('/notifications', (request, response) => {
    const payload = request.body as InternalNotificationPayload;

    io.to(rooms.user(payload.userId)).emit('notification:new', {
      title: payload.title,
      body: payload.body,
      type: payload.type,
      gymId: payload.gymId ?? null,
      branchId: payload.branchId ?? null,
      data: payload.data ?? {},
      createdAt: new Date().toISOString(),
    });

    io.to(rooms.userNotifications(payload.userId)).emit('notification:new', {
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

  return router;
}
