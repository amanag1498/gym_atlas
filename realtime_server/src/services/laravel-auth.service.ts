import jwt from 'jsonwebtoken';
import { env } from '../config/env';
import type { ActiveRole, SocketUserContext } from '../types/auth';
import { apiFetch } from './http';

interface LaravelApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, unknown> | null;
  meta: Record<string, unknown> | null;
}

interface LaravelUserResource {
  id: number;
  name: string;
  email: string;
  active_role: ActiveRole;
  roles: string[];
  permissions: string[];
  gyms?: Array<{ id: number }>;
  branches?: Array<{ id: number; gym_id: number }>;
}

interface MemberContextResponse {
  user: LaravelUserResource;
  trainer_connection?: {
    assigned_trainer?: {
      id: number;
      name: string;
    } | null;
  } | null;
  branches?: Array<{ id: number; gym_id: number }>;
}

interface TrainerAssignedMemberList {
  data: Array<{
    member_id: number;
  }>;
}

interface TrainerContextResponse {
  user: LaravelUserResource;
  trainer_profile?: {
    gym_id: number;
    branch_id: number | null;
  } | null;
  branches?: Array<{ id: number; gym_id: number }>;
}

interface GymContextResponse {
  user: LaravelUserResource;
  gyms?: Array<{ id: number; branches?: Array<{ id: number }> }>;
}

export class LaravelAuthService {
  async verifyAccessToken(token: string): Promise<SocketUserContext> {
    if (env.tokenVerificationStrategy === 'jwt') {
      return this.verifySharedJwt(token);
    }

    const user = await this.fetchSocketUserFromLaravel(token);

    return user;
  }

  private verifySharedJwt(token: string): SocketUserContext {
    if (!env.jwtSharedSecret) {
      throw new Error('JWT_SHARED_SECRET is required for shared JWT strategy.');
    }

    const decoded = jwt.verify(token, env.jwtSharedSecret) as Record<string, unknown>;
    const activeRole = decoded.active_role as ActiveRole | undefined;

    if (!decoded.sub || !activeRole || !Array.isArray(decoded.roles) || !Array.isArray(decoded.permissions)) {
      throw new Error('Invalid JWT payload for socket authentication.');
    }

    return {
      id: Number(decoded.sub),
      name: String(decoded.name ?? ''),
      email: String(decoded.email ?? ''),
      activeRole,
      roles: decoded.roles.map(String),
      permissions: decoded.permissions.map(String),
      gymIds: Array.isArray(decoded.gym_ids) ? decoded.gym_ids.map(Number) : [],
      branchIds: Array.isArray(decoded.branch_ids) ? decoded.branch_ids.map(Number) : [],
      branchScopes: Array.isArray(decoded.branch_scopes)
        ? decoded.branch_scopes.map((scope) => ({
            gymId: Number((scope as Record<string, unknown>).gym_id),
            branchId: Number((scope as Record<string, unknown>).branch_id),
          }))
        : [],
      assignedMemberIds: Array.isArray(decoded.assigned_member_ids) ? decoded.assigned_member_ids.map(Number) : [],
      assignedTrainerId: decoded.assigned_trainer_id ? Number(decoded.assigned_trainer_id) : null,
    };
  }

  private async fetchSocketUserFromLaravel(token: string): Promise<SocketUserContext> {
    const me = await this.fetchProtected<LaravelUserResource>('public/me', token);
    const activeRole = me.active_role;

    if (activeRole === 'member') {
      const context = await this.fetchProtected<MemberContextResponse>('member/context', token);

      return {
        id: context.user.id,
        name: context.user.name,
        email: context.user.email,
        activeRole,
        roles: context.user.roles,
        permissions: context.user.permissions,
        gymIds: (context.user.gyms ?? []).map((gym) => gym.id),
        branchIds: (context.branches ?? context.user.branches ?? []).map((branch) => branch.id),
        branchScopes: (context.branches ?? context.user.branches ?? []).map((branch) => ({
          gymId: branch.gym_id,
          branchId: branch.id,
        })),
        assignedMemberIds: [],
        assignedTrainerId: context.trainer_connection?.assigned_trainer?.id ?? null,
      };
    }

    if (activeRole === 'trainer') {
      const [context, members] = await Promise.all([
        this.fetchProtected<TrainerContextResponse>('trainer/context', token),
        this.fetchPaginated<TrainerAssignedMemberList>('trainer/assigned-members?per_page=1000', token),
      ]);

      return {
        id: context.user.id,
        name: context.user.name,
        email: context.user.email,
        activeRole,
        roles: context.user.roles,
        permissions: context.user.permissions,
        gymIds: (context.user.gyms ?? []).map((gym) => gym.id),
        branchIds: (context.branches ?? context.user.branches ?? []).map((branch) => branch.id),
        branchScopes: (context.branches ?? context.user.branches ?? []).map((branch) => ({
          gymId: branch.gym_id,
          branchId: branch.id,
        })),
        assignedMemberIds: members.data.map((member) => member.member_id),
        assignedTrainerId: null,
      };
    }

    if (activeRole === 'gym_owner' || activeRole === 'branch_manager' || activeRole === 'gym_staff') {
      const context = await this.fetchProtected<GymContextResponse>('gym/context', token);
      const gymIds = (context.gyms ?? context.user.gyms ?? []).map((gym) => gym.id);
      const branchIds = [
        ...(context.user.branches ?? []).map((branch) => branch.id),
        ...(context.gyms ?? []).flatMap((gym) => (gym.branches ?? []).map((branch) => branch.id)),
      ];
      const branchScopes = [
        ...(context.user.branches ?? []).map((branch) => ({
          gymId: branch.gym_id,
          branchId: branch.id,
        })),
        ...(context.gyms ?? []).flatMap((gym) => (gym.branches ?? []).map((branch) => ({
          gymId: gym.id,
          branchId: branch.id,
        }))),
      ];

      return {
        id: context.user.id,
        name: context.user.name,
        email: context.user.email,
        activeRole,
        roles: context.user.roles,
        permissions: context.user.permissions,
        gymIds: [...new Set(gymIds)],
        branchIds: [...new Set(branchIds)],
        branchScopes: branchScopes.filter(
          (scope, index, list) => list.findIndex((item) => item.gymId === scope.gymId && item.branchId === scope.branchId) === index,
        ),
        assignedMemberIds: [],
        assignedTrainerId: null,
      };
    }

    return {
      id: me.id,
      name: me.name,
      email: me.email,
      activeRole,
      roles: me.roles,
      permissions: me.permissions,
      gymIds: (me.gyms ?? []).map((gym) => gym.id),
      branchIds: (me.branches ?? []).map((branch) => branch.id),
      branchScopes: (me.branches ?? []).map((branch) => ({
        gymId: branch.gym_id,
        branchId: branch.id,
      })),
      assignedMemberIds: [],
      assignedTrainerId: null,
    };
  }

  private async fetchProtected<T>(path: string, token: string): Promise<T> {
    const response = await apiFetch<LaravelApiEnvelope<T>>(`${env.laravelApiBaseUrl}/${path}`, {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });

    return response.data;
  }

  private async fetchPaginated<T>(path: string, token: string): Promise<T> {
    return apiFetch<T>(`${env.laravelApiBaseUrl}/${path}`, {
      method: 'GET',
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
  }
}
