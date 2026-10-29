import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Invoice = sequelize.define('Invoice', {
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
    allowNull: true,
  },
  observation: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  series: {
    type: DataTypes.STRING(50),
    allowNull: true,
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
  currency: {
    type: DataTypes.STRING(10),
    allowNull: false,
  },
  manual_exchange_rate: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  total_sum: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  total_discount: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  subtotal_without_tax: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  total_tax: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  final_total: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  converted_total: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  user_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  document_type: {
    type: DataTypes.ENUM('FT', 'FR', 'NC', 'ND', 'FT'),
    allowNull: false,
    defaultValue: 'FT',
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
  numero_validacao: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  hash: {
    type: DataTypes.STRING(200),
    allowNull: true,
  },
  hash_control: {
    type: DataTypes.ENUM('1', '0', '1'),
    allowNull: false,
    defaultValue: '1',
  },
  paid_total: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  cloned_from_invoice_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  clone_count: {
    type: DataTypes.INTEGER,
    allowNull: true,
    defaultValue: 0,
  },
}, {
  tableName: 'invoices',
  timestamps: false,
  underscored: true,
});

export default Invoice;
