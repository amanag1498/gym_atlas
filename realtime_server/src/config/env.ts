import dotenv from 'dotenv';

dotenv.config();

function requireEnv(key: string, fallback?: string): string {
  const value = process.env[key] ?? fallback;

  if (!value) {
    throw new Error(`Missing required environment variable: ${key}`);
  }

  return value;
}

export const env = {
  nodeEnv: process.env.NODE_ENV ?? 'development',
  port: Number(process.env.PORT ?? 4000),
  clientOrigin: process.env.CLIENT_ORIGIN ?? '*',
  laravelApiBaseUrl: requireEnv('LARAVEL_API_BASE_URL', 'https://187.127.162.27:8081//api'),
  socketInternalApiKey: requireEnv('SOCKET_INTERNAL_API_KEY', 'change-me'),
  tokenVerificationStrategy: process.env.TOKEN_VERIFICATION_STRATEGY ?? 'laravel',
  jwtSharedSecret: process.env.JWT_SHARED_SECRET ?? '',
  useRedisAdapter: (process.env.USE_REDIS_ADAPTER ?? 'false') === 'true',
} as const;
