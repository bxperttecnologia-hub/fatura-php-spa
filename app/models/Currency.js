import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Currency = sequelize.define('Currency', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  iso_code: {
    type: DataTypes.STRING(10),
    allowNull: true,
  },
  currency: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  symbol: {
    type: DataTypes.STRING(10),
    allowNull: true,
  },
  position: {
    type: DataTypes.ENUM('left', 'right'),
    allowNull: true,
  },
}, {
  tableName: 'currencies',
  timestamps: false,
  underscored: true,
});

export default Currency;
