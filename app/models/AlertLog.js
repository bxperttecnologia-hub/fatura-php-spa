import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const AlertLog = sequelize.define('AlertLog', {
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
  rule_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  entity_type: {
    type: DataTypes.ENUM('invoice', 'employee', 'payroll'),
    allowNull: false,
  },
  entity_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  step: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  channel: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('sent', 'failed', 'pending', 'pending'),
    allowNull: true,
    defaultValue: 'pending',
  },
  sent_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
  response_meta: {
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
  tableName: 'alert_logs',
  timestamps: false,
  underscored: true,
});

export default AlertLog;
