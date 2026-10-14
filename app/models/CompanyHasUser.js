import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CompanyHasUser = sequelize.define('CompanyHasUser', {
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
  user_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  role: {
    type: DataTypes.ENUM('owner', 'admin', 'employee', 'viewer'),
    allowNull: true,
    defaultValue: 'employee',
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
  tableName: 'company_has_user',
  timestamps: false,
  underscored: true,
});

export default CompanyHasUser;
