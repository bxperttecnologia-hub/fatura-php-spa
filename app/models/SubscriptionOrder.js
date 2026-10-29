import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const SubscriptionOrder = sequelize.define('SubscriptionOrder', {
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
  plan_code: {
    type: DataTypes.STRING(40),
    allowNull: false,
  },
  period_months: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 3,
  },
  amount: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
  status: {
    type: DataTypes.ENUM('pending', 'paid', 'canceled', 'pending'),
    allowNull: false,
    defaultValue: 'pending',
  },
  gateway: {
    type: DataTypes.STRING(30),
    allowNull: true,
  },
  payment_method: {
    type: DataTypes.STRING(10),
    allowNull: true,
  },
  merchant_transaction_id: {
    type: DataTypes.STRING(80),
    allowNull: true,
  },
  gateway_charge_id: {
    type: DataTypes.STRING(80),
    allowNull: true,
  },
  gateway_status: {
    type: DataTypes.STRING(40),
    allowNull: true,
  },
  gateway_response_raw: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  payment_provider: {
    type: DataTypes.STRING(40),
    allowNull: true,
  },
  payment_ref: {
    type: DataTypes.STRING(120),
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
  paid_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
}, {
  tableName: 'subscription_orders',
  timestamps: false,
  underscored: true,
});

export default SubscriptionOrder;
