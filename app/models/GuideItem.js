import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const GuideItem = sequelize.define('GuideItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    primaryKey: true,
  },
  guide_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  description: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  unit_price: {
    type: DataTypes.DECIMAL,
    allowNull: false,
  },
  quantity: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 1,
  },
  tax: {
    type: DataTypes.STRING(8),
    allowNull: false,
  },
  discount: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
}, {
  tableName: 'guide_items',
  timestamps: false,
  underscored: true,
});

export default GuideItem;
