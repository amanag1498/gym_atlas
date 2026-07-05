import type { Socket } from 'socket.io';
import { rooms } from '../socket/rooms';
import type { SocketUserContext } from '../types/auth';
import { resolveTrainerMemberRoom } from '../socket/rooms';

export class RoomService {
  async joinBaseRooms(socket: Socket, user: SocketUserContext): Promise<void> {
    const joinTargets = new Set<string>();
    joinTargets.add(rooms.user(user.id));
    joinTargets.add(rooms.userNotifications(user.id));
    joinTargets.add(rooms.userPresence(user.id));
    joinTargets.add(rooms.role(user.activeRole));

    if (user.activeRole === 'platform_admin') {
      joinTargets.add(rooms.platformAnnouncements());
    }

    for (const gymId of user.gymIds) {
      joinTargets.add(rooms.gymAnnouncements(gymId));
    }

    for (const scope of user.branchScopes) {
      joinTargets.add(rooms.branchAnnouncements(scope.gymId, scope.branchId));
    }

    if (user.activeRole === 'trainer') {
      for (const memberId of user.assignedMemberIds) {
        joinTargets.add(resolveTrainerMemberRoom(user.id, memberId));
        joinTargets.add(rooms.userPresence(memberId));
      }
    }

    if (user.activeRole === 'member' && user.assignedTrainerId) {
      joinTargets.add(resolveTrainerMemberRoom(user.assignedTrainerId, user.id));
      joinTargets.add(rooms.userPresence(user.assignedTrainerId));
    }

    await socket.join([...joinTargets]);
  }
}
