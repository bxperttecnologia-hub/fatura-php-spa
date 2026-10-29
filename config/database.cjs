require('dotenv').config();

const toBool = (value, fallback = false) => {
  if (value == null || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
};

const base = (databaseName) => ({
  username: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: databaseName,
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  dialect: 'mysql',
  timezone: '+00:00',
  logging: false,
  dialectOptions: {
    charset: 'utf8mb4',
    connectTimeout: 10000,
    ...(toBool(process.env.DB_SSL)
      ? { ssl: { rejectUnauthorized: toBool(process.env.DB_SSL_REJECT_UNAUTHORIZED, true) } }
      : {}),
  },
});

const name = process.env.DB_NAME || 'u523793545_sistema_fatura';

module.exports = {
  development: base(name),
  test: base(process.env.DB_NAME_TEST || `${name}_test`),
  production: base(name),
};
