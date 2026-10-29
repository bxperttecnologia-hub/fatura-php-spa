import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Proforma = sequelize.define('Proforma', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  contact_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  user_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  status: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 1,
  },
  issue_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  due_date: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  reference: {
    type: DataTypes.STRING(100),
    allowNull: false,
  },
  observation: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  series: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  currency: {
    type: DataTypes.STRING(10),
    allowNull: false,
  },
  manual_exchange_rate: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 1,
  },
  retention: {
    type: DataTypes.DOUBLE,
    allowNull: true,
    defaultValue: 0,
  },
  retention_value: {
    type: DataTypes.DOUBLE,
    allowNull: true,
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
  converted_total: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  converted_invoice_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
  converted_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
}, {
  tableName: 'proformas',
  timestamps: false,
  underscored: true,
});

export default Proforma;
