import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Payment = sequelize.define('Payment', {
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
  plan_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  amount: {
    type: DataTypes.DECIMAL,
    allowNull: false,
  },
  reference: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  payment_method: {
    type: DataTypes.ENUM('reference', 'qrcode'),
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('pending', 'paid', 'failed', 'pending'),
    allowNull: false,
    defaultValue: 'pending',
  },
  transaction_id: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'payments',
  timestamps: false,
  underscored: true,
});

export default Payment;
