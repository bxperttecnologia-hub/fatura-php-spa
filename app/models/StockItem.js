import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const StockItem = sequelize.define('StockItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    primaryKey: true,
  },
  stock_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  quantity: {
    type: DataTypes.INTEGER,
    allowNull: true,
    defaultValue: 0,
  },
  min_quantity: {
    type: DataTypes.INTEGER,
    allowNull: true,
    defaultValue: 1,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'stock_items',
  timestamps: false,
  underscored: true,
});

export default StockItem;
