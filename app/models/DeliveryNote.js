import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const DeliveryNote = sequelize.define('DeliveryNote', {
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
    defaultValue: 'NE',
  },
  number: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  issue_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  delivery_address: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  notes: {
    type: DataTypes.TEXT,
    allowNull: true,
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
  tableName: 'delivery_notes',
  timestamps: false,
  underscored: true,
});

export default DeliveryNote;
