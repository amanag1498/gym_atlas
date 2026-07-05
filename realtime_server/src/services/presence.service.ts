import { Server } from 'socket.io';
import { rooms } from '../socket/rooms';
import type { SocketUserContext } from '../types/auth';

interface PresenceState {
  status: 'online' | 'offline' | 'away';
  sockets: Set<string>;
  updatedAt: string;
}

export class PresenceService {
  private readonly states = new Map<number, PresenceState>();

  registerConnection(io: Server, user: SocketUserContext, socketId: string): void {
    const existing = this.states.get(user.id);
    const next: PresenceState = existing ?? {
      status: 'online',
      sockets: new Set<string>(),
      updatedAt: new Date().toISOString(),
    };

    next.sockets.add(socketId);
    next.status = 'online';
    next.updatedAt = new Date().toISOString();
    this.states.set(user.id, next);

    this.broadcast(io, user.id, next.status, next.updatedAt);
  }

  unregisterConnection(io: Server, user: SocketUserContext, socketId: string): void {
    const state = this.states.get(user.id);
    if (!state) {
      return;
    }

    state.sockets.delete(socketId);

    if (state.sockets.size === 0) {
      state.status = 'offline';
      state.updatedAt = new Date().toISOString();
      this.states.set(user.id, state);
      this.broadcast(io, user.id, state.status, state.updatedAt);
      return;
    }

    this.states.set(user.id, state);
  }

  updateStatus(io: Server, userId: number, status: 'online' | 'offline' | 'away'): void {
    const state = this.states.get(userId) ?? {
      status,
      sockets: new Set<string>(),
      updatedAt: new Date().toISOString(),
    };

    state.status = status;
    state.updatedAt = new Date().toISOString();
    this.states.set(userId, state);
    this.broadcast(io, userId, state.status, state.updatedAt);
  }

  isOnline(userId: number): boolean {
    const state = this.states.get(userId);
    return Boolean(state && state.status === 'online' && state.sockets.size > 0);
  }

  private broadcast(io: Server, userId: number, status: 'online' | 'offline' | 'away', updatedAt: string): void {
    io.to(rooms.userPresence(userId)).emit('presence:update', {
      userId,
      status,
      updatedAt,
    });
    io.to(rooms.user(userId)).emit('presence:update', {
      userId,
      status,
      updatedAt,
    });
  }
}
