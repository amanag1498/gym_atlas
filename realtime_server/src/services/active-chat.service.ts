export class ActiveChatService {
  private readonly activeRooms = new Map<number, Map<string, string>>();

  setActive(userId: number, socketId: string, room: string): void {
    const sockets = this.activeRooms.get(userId) ?? new Map<string, string>();
    sockets.set(socketId, room);
    this.activeRooms.set(userId, sockets);
  }

  clear(userId: number, socketId: string, room?: string): void {
    const sockets = this.activeRooms.get(userId);
    if (!sockets) {
      return;
    }

    if (room === undefined || sockets.get(socketId) === room) {
      sockets.delete(socketId);
    }

    if (sockets.size === 0) {
      this.activeRooms.delete(userId);
    }
  }

  isActive(userId: number, room: string): boolean {
    return Array.from(this.activeRooms.get(userId)?.values() ?? [])
      .some((activeRoom) => activeRoom === room);
  }
}
