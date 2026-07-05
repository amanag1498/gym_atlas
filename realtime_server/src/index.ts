import cors from 'cors';
import express from 'express';
import http from 'http';
import { Server } from 'socket.io';
import { env } from './config/env';
import { internalApiMiddleware } from './middleware/internal-api.middleware';
import { socketAuthMiddleware } from './middleware/socket-auth.middleware';
import { buildInternalRoutes } from './routes/internal.routes';
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
  logger.info('Redis adapter placeholder enabled. Attach @socket.io/redis-adapter here when Redis is introduced.');
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
      redisAdapterReady: env.useRedisAdapter,
    },
  });
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
