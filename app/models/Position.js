import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Position = sequelize.define('Position', {
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
  name: {
    type: DataTypes.STRING(100),
    allowNull: false,
  },
  suggested_salary: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  food_allowance: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  transport_allowance: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  vacation_subsidy_pct: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  thirteenth_subsidy_pct: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  department_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
}, {
  tableName: 'positions',
  timestamps: false,
  underscored: true,
});

export default Position;
