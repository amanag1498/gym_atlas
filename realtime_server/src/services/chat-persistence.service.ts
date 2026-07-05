import { env } from '../config/env';
import type { ChatReadPayload, ChatSendPayload } from '../types/socket';
import { apiFetch } from './http';
import { logger } from './logger';

export interface PersistedChatMessage {
  id: string;
  room: string;
  senderId: number;
  recipientId: number;
  body: string;
  clientMessageId?: string;
  metadata?: Record<string, unknown>;
  createdAt: string;
  persisted: boolean;
}

export class ChatPersistenceService {
  async persistMessage(
    room: string,
    senderId: number,
    payload: ChatSendPayload,
    options: { suppressPush?: boolean } = {},
  ): Promise<PersistedChatMessage> {
    try {
      const [trainerId, memberId] = this.parseRoom(room);
      const response = await apiFetch<{
        success: boolean;
        data: {
          id: string;
          room: string;
          sender_id: number;
          recipient_id: number;
          body: string;
          client_message_id?: string | null;
          metadata?: Record<string, unknown> | null;
          created_at?: string | null;
        };
      }>(`${env.laravelApiBaseUrl}/internal/chat/messages`, {
        method: 'POST',
        headers: {
          'X-Internal-Api-Key': env.socketInternalApiKey,
        },
        body: JSON.stringify({
          room,
          trainer_id: trainerId,
          member_id: memberId,
          sender_id: senderId,
          recipient_id: payload.recipientId,
          message: payload.message,
          client_message_id: payload.clientMessageId,
          metadata: payload.metadata,
          suppress_push: options.suppressPush === true,
        }),
      });

      const persistedMessage: PersistedChatMessage = {
        id: response.data.id,
        room: response.data.room,
        senderId: response.data.sender_id,
        recipientId: response.data.recipient_id,
        body: response.data.body,
        createdAt: response.data.created_at ?? new Date().toISOString(),
        persisted: true,
      };

      const clientMessageId = response.data.client_message_id ?? payload.clientMessageId;
      if (clientMessageId) {
        persistedMessage.clientMessageId = clientMessageId;
      }

      const metadata = response.data.metadata ?? payload.metadata;
    if (metadata) {
      persistedMessage.metadata = metadata;
    }

      return persistedMessage;
    } catch (error) {
      logger.error('Chat persistence failed; rejecting socket message', {
        room,
        senderId,
        recipientId: payload.recipientId,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
      throw error;
    }
  }

  async persistReadReceipt(
    room: string,
    userId: number,
    payload: ChatReadPayload,
  ): Promise<{ room: string; userId: number; messageIds: string[]; readAt: string; persisted: boolean }> {
    let persisted = false;
    try {
      await apiFetch(`${env.laravelApiBaseUrl}/internal/chat/read`, {
        method: 'POST',
        headers: {
          'X-Internal-Api-Key': env.socketInternalApiKey,
        },
        body: JSON.stringify({
          room,
          user_id: userId,
          message_ids: payload.messageIds,
        }),
      });
      persisted = true;
    } catch (error) {
      logger.warn('Chat read receipt persistence failed', {
        room,
        userId,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }

    return {
      room,
      userId,
      messageIds: payload.messageIds,
      readAt: payload.readAt ?? new Date().toISOString(),
      persisted,
    };
  }

  private parseRoom(room: string): [number, number] {
    const match = /^trainer:(\d+):member:(\d+)$/.exec(room);
    if (!match) {
      throw new Error(`Invalid trainer-member chat room: ${room}`);
    }

    return [Number(match[1]), Number(match[2])];
  }
}
