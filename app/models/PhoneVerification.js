import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const PhoneVerification = sequelize.define('PhoneVerification', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  name: {
    type: DataTypes.STRING(150),
    allowNull: false,
  },
  phone: {
    type: DataTypes.STRING(20),
    allowNull: false,
  },
  channel: {
    type: DataTypes.ENUM('sms', 'whatsapp'),
    allowNull: false,
  },
  code_hash: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  attempts: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 0,
  },
  ip: {
    type: DataTypes.STRING(45),
    allowNull: true,
  },
  expires_at: {
    type: DataTypes.DATE,
    allowNull: false,
  },
  verified_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
  },
}, {
  tableName: 'phone_verifications',
  timestamps: false,
  underscored: true,
});

export default PhoneVerification;
