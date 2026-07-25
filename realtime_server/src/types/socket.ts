import type { SocketUserContext } from './auth';

export interface ChatSendPayload {
  recipientId: number;
  message: string;
  clientMessageId?: string;
  metadata?: Record<string, unknown>;
}

export interface ChatReadPayload {
  recipientId: number;
  messageIds: string[];
  readAt?: string;
}

export interface PresenceUpdatePayload {
  status?: 'online' | 'offline' | 'away';
}

export interface InternalNotificationPayload {
  userId: number;
  gymId?: number | null;
  branchId?: number | null;
  title: string;
  body: string;
  type: string;
  data?: Record<string, unknown>;
}

export interface InternalAnnouncementPayload {
  audience: 'platform' | 'gym' | 'branch';
  gymId?: number | null;
  branchId?: number | null;
  title: string;
  message: string;
  data?: Record<string, unknown>;
}

export interface InternalChatMessagePayload {
  room: string;
  trainerId: number;
  memberId: number;
  message: PersistedChatEventMessage;
}

export interface InternalChatReadPayload {
  room: string;
  userId: number;
  recipientId: number;
  messageIds: string[];
  readAt: string;
}

export interface PersistedChatEventMessage {
  id: string;
  room: string;
  senderId: number;
  recipientId: number;
  body: string;
  clientMessageId?: string | null;
  metadata?: Record<string, unknown>;
  createdAt: string;
  persisted: true;
}

export interface AuthenticatedSocketData {
  user: SocketUserContext;
}
