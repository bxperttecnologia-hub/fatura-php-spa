import rateLimit from 'express-rate-limit';

export const createApiLimiter = () => rateLimit({
  windowMs: 15 * 60 * 1000,
  max: Number(process.env.API_RATE_LIMIT_MAX || 1000),
  standardHeaders: true,
  legacyHeaders: false,
  message: {
    success: false,
    message: 'Muitas solicitações. Tente novamente mais tarde.',
    errors: ['Too many requests'],
  },
});

export const createAuthLimiter = () => rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  standardHeaders: true,
  legacyHeaders: false,
  message: {
    success: false,
    message: 'Tentativas de login excedidas. Tente novamente em 15 minutos.',
    errors: ['Rate limit exceeded'],
  },
});
