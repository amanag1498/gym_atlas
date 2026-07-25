import cors from 'cors';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { env } from './config/env';
import { internalApiMiddleware } from './middleware/internal-api.middleware';
import { socketAuthMiddleware } from './middleware/socket-auth.middleware';
import { buildInternalRoutes } from './routes/internal.routes';
import { apiFetch } from './services/http';
import { logger } from './services/logger';
import { registerSocketServer } from './socket/socket.server';

const app = express();
const server = http.createServer(app);

const io = new Server(server, {
  cors: {
    origin: env.clientOrigin === '*' ? true : env.clientOrigin,
    credentials: true,
  },
});

if (env.useRedisAdapter) {
  throw new Error('USE_REDIS_ADAPTER=true is not supported until a Redis adapter is configured.');
}

app.use(cors({
  origin: env.clientOrigin === '*' ? true : env.clientOrigin,
  credentials: true,
}));
app.use(express.json());

app.get('/health', (_request, response) => {
  response.json({
    success: true,
    message: 'Realtime server healthy.',
    data: {
      nodeEnv: env.nodeEnv,
      redisAdapterReady: false,
      uptimeSeconds: Math.floor(process.uptime()),
    },
  });
});

app.get('/ready', async (_request, response) => {
  try {
    await apiFetch(`${env.laravelApiBaseUrl}/internal/chat/health`, {
      method: 'GET',
      headers: {
        'X-Internal-Api-Key': env.socketInternalApiKey,
      },
      signal: AbortSignal.timeout(5000),
    });
    response.json({
      success: true,
      message: 'Realtime server is ready.',
      data: {
        laravelApi: 'reachable',
      },
    });
  } catch (error) {
    logger.error('Realtime readiness check failed', {
      error: error instanceof Error ? error.message : 'Unknown error',
    });
    response.status(503).json({
      success: false,
      message: 'Realtime persistence dependency is unavailable.',
      data: {
        laravelApi: 'unreachable',
      },
    });
  }
});

app.use('/internal', internalApiMiddleware, buildInternalRoutes(io));

io.use(socketAuthMiddleware);
registerSocketServer(io);

server.listen(env.port, () => {
  logger.info('Realtime server started', {
    port: env.port,
    laravelApiBaseUrl: env.laravelApiBaseUrl,
    tokenVerificationStrategy: env.tokenVerificationStrategy,
  });
});

function shutdown(signal: string): void {
  logger.info('Realtime server shutting down', { signal });
  io.close();
  server.close((error) => {
    if (error) {
      logger.error('Realtime server shutdown failed', { error: error.message });
      process.exitCode = 1;
    }
  });
}

process.once('SIGTERM', () => shutdown('SIGTERM'));
process.once('SIGINT', () => shutdown('SIGINT'));
