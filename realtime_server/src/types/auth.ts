export type ActiveRole =
  | 'platform_admin'
  | 'gym_owner'
  | 'branch_manager'
  | 'gym_staff'
  | 'trainer'
  | 'member';

export interface TrainerConnection {
  assignedTrainer: {
    id: number;
    name: string;
  } | null;
}

export interface SocketUserContext {
  id: number;
  name: string;
  email: string;
  activeRole: ActiveRole;
  roles: string[];
  permissions: string[];
  gymIds: number[];
  branchIds: number[];
  branchScopes: Array<{
    gymId: number;
    branchId: number;
  }>;
  assignedMemberIds: number[];
  assignedTrainerId: number | null;
}
