import type { SocketUserContext } from '../types/auth';
import { resolveTrainerMemberRoom } from '../socket/rooms';

export interface AuthorizedChatPeer {
  recipientId: number;
  trainerId: number;
  memberId: number;
  room: string;
}

export class ChatAuthorizationService {
  authorizePeer(user: SocketUserContext, recipientId: number): AuthorizedChatPeer {
    if (user.activeRole === 'trainer') {
      if (!user.assignedMemberIds.includes(recipientId)) {
        throw new Error('Trainer can chat only with assigned members.');
      }

      return {
        recipientId,
        trainerId: user.id,
        memberId: recipientId,
        room: resolveTrainerMemberRoom(user.id, recipientId),
      };
    }

    if (user.activeRole === 'member') {
      if (user.assignedTrainerId !== recipientId) {
        throw new Error('Member can chat only with the assigned trainer.');
      }

      return {
        recipientId,
        trainerId: recipientId,
        memberId: user.id,
        room: resolveTrainerMemberRoom(recipientId, user.id),
      };
    }

    throw new Error('This role cannot access trainer-member chat.');
  }
}
