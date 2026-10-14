import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const SupportAgent = sequelize.define('SupportAgent', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  name: {
    type: DataTypes.STRING(150),
    allowNull: false,
  },
  email: {
    type: DataTypes.STRING(180),
    allowNull: false,
  },
  password_hash: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  role: {
    type: DataTypes.ENUM('commercial_support', 'support_admin', 'commercial_support'),
    allowNull: false,
    defaultValue: 'commercial_support',
  },
  active: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 1,
  },
  last_login_at: {
    type: DataTypes.DATE,
    allowNull: true,
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
  tableName: 'support_agents',
  timestamps: false,
  underscored: true,
});

export default SupportAgent;
