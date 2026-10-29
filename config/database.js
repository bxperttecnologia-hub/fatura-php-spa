import 'dotenv/config';
import { Sequelize } from 'sequelize';
import { appConfig } from './app.js';

const toBool = (value, fallback = false) => {
  if (value == null || value === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(String(value).trim().toLowerCase());
};

const useSsl = toBool(process.env.DB_SSL);

export const databaseConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  database: process.env.DB_NAME || 'u523793545_sistema_fatura',
  username: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  dialect: 'mysql',
  timezone: '+00:00', // a versão PHP fazia SET time_zone = '+00:00'
  logging: appConfig.env === 'development' && toBool(process.env.DB_LOGGING) ? console.log : false,
  define: {
    underscored: true,
    timestamps: true,
  },
  dialectOptions: {
    charset: 'utf8mb4',
    connectTimeout: 10000,
    ...(useSsl
      ? { ssl: { rejectUnauthorized: toBool(process.env.DB_SSL_REJECT_UNAUTHORIZED, true) } }
      : {}),
  },
  pool: {
    max: 10,
    min: 0,
    acquire: 30000,
    idle: 10000,
  },
};

export const sequelize = new Sequelize(
  databaseConfig.database,
  databaseConfig.username,
  databaseConfig.password,
  {
    host: databaseConfig.host,
    port: databaseConfig.port,
    dialect: databaseConfig.dialect,
    timezone: databaseConfig.timezone,
    logging: databaseConfig.logging,
    define: databaseConfig.define,
    dialectOptions: databaseConfig.dialectOptions,
    pool: databaseConfig.pool,
  },
);

/** Traduz erros do driver numa mensagem acionável (sem expor a palavra-passe). */
export const describeDatabaseError = (error) => {
  const code = error?.original?.code || error?.parent?.code || error?.code;
  const target = `${databaseConfig.host}:${databaseConfig.port}`;

  switch (code) {
    case 'ECONNREFUSED':
      return `Ligação recusada em ${target}. Confirme que o MySQL/MariaDB está a correr e que DB_HOST/DB_PORT estão corretos.`;
    case 'ENOTFOUND':
      return `O anfitrião "${databaseConfig.host}" não foi encontrado. Verifique DB_HOST.`;
    case 'ETIMEDOUT':
    case 'EHOSTUNREACH':
      return `Tempo esgotado a contactar ${target}. Verifique rede/firewall e se o servidor aceita ligações remotas.`;
    case 'ER_ACCESS_DENIED_ERROR':
      return `Acesso negado ao utilizador "${databaseConfig.username}". Verifique DB_USER e DB_PASSWORD.`;
    case 'ER_BAD_DB_ERROR':
      return `A base de dados "${databaseConfig.database}" não existe. Crie-a ou corrija DB_NAME.`;
    case 'ER_NOT_SUPPORTED_AUTH_MODE':
      return 'Modo de autenticação MySQL não suportado pelo driver. Use mysql_native_password ou caching_sha2_password com mysql2 atual.';
    case 'HANDSHAKE_SSL_ERROR':
      return 'Falha no handshake SSL. Ajuste DB_SSL / DB_SSL_REJECT_UNAUTHORIZED conforme o servidor.';
    default:
      return error?.message || 'Erro desconhecido ao ligar à base de dados.';
  }
};

export const connectDatabase = async () => {
  try {
    await sequelize.authenticate();
    return sequelize;
  } catch (error) {
    console.error('Database connection failed:', describeDatabaseError(error));
    throw error;
  }
};

export default databaseConfig;
