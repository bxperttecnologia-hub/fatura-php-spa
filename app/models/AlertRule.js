import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const AlertRule = sequelize.define('AlertRule', {
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
  type: {
    type: DataTypes.ENUM('invoice_due', 'payroll_open', 'payroll_close', 'contract_expiry'),
    allowNull: false,
  },
  threshold_days: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 7,
  },
  channels: {
    type: DataTypes.JSON,
    allowNull: false,
  },
  active: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 1,
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
  tableName: 'alert_rules',
  timestamps: false,
  underscored: true,
});

export default AlertRule;
