import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const DebitNote = sequelize.define('DebitNote', {
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
  serie: {
    type: DataTypes.STRING(10),
    allowNull: false,
    defaultValue: 'ND',
  },
  number: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  issue_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  reason: {
    type: DataTypes.TEXT,
    allowNull: false,
  },
  currency: {
    type: DataTypes.STRING(10),
    allowNull: false,
  },
  subtotal_without_tax: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
  total_tax: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
  final_total: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
  user_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'debit_notes',
  timestamps: false,
  underscored: true,
});

export default DebitNote;
