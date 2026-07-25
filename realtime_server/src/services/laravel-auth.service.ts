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

interface RealtimeContextResponse {
  id: number;
  name: string;
  email: string;
  active_role: ActiveRole;
  roles: string[];
  permissions: string[];
  gym_ids: number[];
  branch_ids: number[];
  branch_scopes: Array<{ gym_id: number; branch_id: number }>;
  assigned_member_ids: number[];
  assigned_trainer_id: number | null;
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
    const context = await this.fetchProtected<RealtimeContextResponse>('public/realtime/context', token);

    return {
      id: context.id,
      name: context.name,
      email: context.email,
      activeRole: context.active_role,
      roles: context.roles,
      permissions: context.permissions,
      gymIds: context.gym_ids,
      branchIds: context.branch_ids,
      branchScopes: context.branch_scopes.map((scope) => ({
        gymId: scope.gym_id,
        branchId: scope.branch_id,
      })),
      assignedMemberIds: context.assigned_member_ids,
      assignedTrainerId: context.assigned_trainer_id,
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
}
