import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CreditNote = sequelize.define('CreditNote', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  invoice_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  contact_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  issue_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  reason: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  currency: {
    type: DataTypes.STRING(10),
    allowNull: false,
  },
  retention: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  retention_value: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  total_sum: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  total_discount: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  subtotal_without_tax: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  total_tax: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  final_total: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  user_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'credit_notes',
  timestamps: false,
  underscored: true,
});

export default CreditNote;
