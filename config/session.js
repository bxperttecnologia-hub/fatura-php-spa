import 'dotenv/config';
import crypto from 'node:crypto';

const isProduction = process.env.NODE_ENV === 'production';

const resolveSecret = () => {
  if (process.env.SESSION_SECRET) return process.env.SESSION_SECRET;
  if (isProduction) {
    throw new Error('SESSION_SECRET é obrigatório em produção.');
  }
  console.warn('[session] SESSION_SECRET não definido: a usar segredo temporário (as sessões perdem-se ao reiniciar).');
  return crypto.randomBytes(32).toString('hex');
};

// 'auto' só marca o cookie como Secure quando o pedido chega por HTTPS
// (atrás de proxy requer TRUST_PROXY=true). Evita "login ok mas sessão perdida" em HTTP.
const resolveSecureFlag = () => {
  const raw = (process.env.COOKIE_SECURE || '').trim().toLowerCase();
  if (['1', 'true', 'yes'].includes(raw)) return true;
  if (['0', 'false', 'no'].includes(raw)) return false;
  return isProduction ? 'auto' : false;
};

export const SESSION_DEFAULT_MAX_AGE = 1000 * 60 * 60 * 8;
export const SESSION_REMEMBER_MAX_AGE = 1000 * 60 * 60 * 24;

export const sessionConfig = {
  name: process.env.SESSION_NAME || 'dashboard.sid',
  secret: resolveSecret(),
  resave: false,
  saveUninitialized: false,
  rolling: true,
  cookie: {
    httpOnly: true,
    secure: resolveSecureFlag(),
    sameSite: 'lax',
    maxAge: SESSION_DEFAULT_MAX_AGE,
  },
};
