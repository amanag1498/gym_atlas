import type { NextFunction, Request, Response } from 'express';
import { env } from '../config/env';

export function internalApiMiddleware(request: Request, response: Response, next: NextFunction): void {
  const apiKey = request.header('x-internal-api-key');

  if (!apiKey || apiKey !== env.socketInternalApiKey) {
    response.status(401).json({
      success: false,
      message: 'Unauthorized internal request.',
    });

    return;
  }

  request.internalPublisher = { service: 'laravel' };
  next();
}
