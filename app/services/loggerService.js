import pino from 'pino';

export const createDefaultLogger = () => pino({
  level: process.env.LOG_LEVEL || 'info',
  base: undefined,
  timestamp: pino.stdTimeFunctions.isoTime,
});

export default createDefaultLogger;
