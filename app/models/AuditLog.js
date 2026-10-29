import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const AuditLog = sequelize.define('AuditLog', {
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
  actor_type: {
    type: DataTypes.ENUM('sistema', 'utilizador', 'autopilot', 'sistema'),
    allowNull: false,
    defaultValue: 'sistema',
  },
  actor_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  action: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  entity_type: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  entity_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  details: {
    type: DataTypes.JSON,
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
  tableName: 'audit_logs',
  timestamps: false,
  underscored: true,
});

export default AuditLog;
