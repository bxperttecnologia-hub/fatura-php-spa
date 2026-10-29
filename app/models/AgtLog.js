import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const AgtLog = sequelize.define('AgtLog', {
  id: {
    type: DataTypes.BIGINT,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  tipo: {
    type: DataTypes.ENUM('SERIE', 'FATURA', 'ESTADO'),
    allowNull: false,
  },
  ambiente: {
    type: DataTypes.ENUM('test', 'production', 'test'),
    allowNull: false,
    defaultValue: 'test',
  },
  nif: {
    type: DataTypes.STRING(20),
    allowNull: false,
  },
  nome_empresa: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  document_type: {
    type: DataTypes.STRING(2),
    allowNull: true,
  },
  document_no: {
    type: DataTypes.STRING(60),
    allowNull: true,
  },
  establishment: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  submission_uuid: {
    type: DataTypes.STRING(36),
    allowNull: true,
  },
  request_id: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  sucesso: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 0,
  },
  http_status_agt: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  mensagem_erro: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  request_payload: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  response_payload: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  criado_em: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: 'current_timestamp(3)',
  },
}, {
  tableName: 'agt_logs',
  timestamps: false,
  underscored: true,
});

export default AgtLog;
