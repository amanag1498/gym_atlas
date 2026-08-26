export const rooms = {
  user: (userId: number): string => `user:${userId}`,
  userNotifications: (userId: number): string => `user:${userId}:notifications`,
  userPresence: (userId: number): string => `user:${userId}:presence`,
  role: (role: string): string => `role:${role}`,
  gymAnnouncements: (gymId: number): string => `gym:${gymId}:announcements`,
  branchAnnouncements: (gymId: number, branchId: number): string => `gym:${gymId}:branch:${branchId}:announcements`,
  platformAnnouncements: (): string => 'platform:announcements',
  platformOperations: (): string => 'platform:operations',
  gymOperations: (gymId: number): string => `gym:${gymId}:operations`,
  branchOperations: (gymId: number, branchId: number): string => `gym:${gymId}:branch:${branchId}:operations`,
  trainerMemberChat: (trainerId: number, memberId: number): string => `trainer:${trainerId}:member:${memberId}`,
};

export function resolveTrainerMemberRoom(trainerId: number, memberId: number): string {
  return rooms.trainerMemberChat(trainerId, memberId);
}
