import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CustomerScore = sequelize.define('CustomerScore', {
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
  contact_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  total_invoices: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 0,
  },
  total_purchased: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  avg_ticket: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  avg_frequency_days: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  avg_delay_days: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  first_purchase_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  last_purchase_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  days_since_last_purchase: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  trend_pct: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  risk_level: {
    type: DataTypes.ENUM('baixo', 'medio', 'alto', 'baixo'),
    allowNull: false,
    defaultValue: 'baixo',
  },
  potential_level: {
    type: DataTypes.ENUM('baixo', 'medio', 'alto', 'muito_alto', 'baixo'),
    allowNull: false,
    defaultValue: 'baixo',
  },
  classifications: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  calculated_at: {
    type: DataTypes.DATE,
    allowNull: false,
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
  tableName: 'customer_scores',
  timestamps: false,
  underscored: true,
});

export default CustomerScore;
