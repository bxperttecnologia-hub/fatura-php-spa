import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CopilotAction = sequelize.define('CopilotAction', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  conversation_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  message_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  action_type: {
    type: DataTypes.ENUM('send_email_collection', 'send_whatsapp_collection', 'send_report_email'),
    allowNull: false,
  },
  payload: {
    type: DataTypes.JSON,
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('pendente', 'aprovada', 'rejeitada', 'executada', 'falhou', 'pendente'),
    allowNull: false,
    defaultValue: 'pendente',
  },
  authorized_by: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  result: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  executed_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
  createdAt: {
    type: DataTypes.DATE,
    allowNull: false,
  },
  updatedAt: {
    type: DataTypes.DATE,
    allowNull: false,
  },
}, {
  tableName: 'copilot_actions',
  timestamps: false,
  underscored: true,
});

export default CopilotAction;
