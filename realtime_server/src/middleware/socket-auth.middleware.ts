import type { Socket } from 'socket.io';
import { LaravelAuthService } from '../services/laravel-auth.service';
import { logger } from '../services/logger';

const authService = new LaravelAuthService();

function extractToken(socket: Socket): string | null {
  const authToken = socket.handshake.auth.token;
  if (typeof authToken === 'string' && authToken.length > 0) {
    return authToken;
  }

  const authorization = socket.handshake.headers.authorization;
  if (typeof authorization === 'string' && authorization.startsWith('Bearer ')) {
    return authorization.slice(7);
  }

  return null;
}

export async function socketAuthMiddleware(socket: Socket, next: (error?: Error) => void): Promise<void> {
  try {
    const token = extractToken(socket);

    if (!token) {
      return next(new Error('Missing authentication token.'));
    }

    const user = await authService.verifyAccessToken(token);
    socket.data.user = user;

    return next();
  } catch (error) {
    logger.warn('Socket authentication failed', {
      socketId: socket.id,
      error: error instanceof Error ? error.message : 'Unknown error',
    });

    return next(new Error('Socket authentication failed.'));
  }
}
