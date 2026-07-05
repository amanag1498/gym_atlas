import type { SocketUserContext } from './auth';

declare global {
  namespace Express {
    interface Request {
      internalPublisher?: {
        service: 'laravel';
      };
      socketUser?: SocketUserContext;
    }
  }
}

export {};
