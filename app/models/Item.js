import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Item = sequelize.define('Item', {
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
  code: {
    type: DataTypes.STRING(50),
    allowNull: false,
  },
  name: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  description: {
    type: DataTypes.TEXT,
    allowNull: false,
  },
  item_type: {
    type: DataTypes.ENUM('product', 'service', 'raw_material', 'finished_good', 'consumable', 'product'),
    allowNull: false,
    defaultValue: 'product',
  },
  unit_measure: {
    type: DataTypes.ENUM('unit', 'kg', 'g', 'liter', 'meter', 'box', 'pack', 'service', 'unit'),
    allowNull: false,
    defaultValue: 'unit',
  },
  category: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  brand: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  barcode: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  sku: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  retention: {
    type: DataTypes.FLOAT,
    allowNull: false,
    defaultValue: 0,
  },
  unit_price: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  currency: {
    type: DataTypes.STRING(10),
    allowNull: false,
    defaultValue: 'AOA',
  },
  track_stock: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 1,
  },
  global_min_stock: {
    type: DataTypes.INTEGER,
    allowNull: true,
    defaultValue: 1,
  },
  status: {
    type: DataTypes.ENUM('active', 'inactive', 'archived', 'active'),
    allowNull: false,
    defaultValue: 'active',
  },
  created_by: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  updated_by: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  tax: {
    type: DataTypes.ENUM('0', '5', '7', '14', '0'),
    allowNull: true,
    defaultValue: '0',
  },
  cost_price: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  sale_price: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  pvp: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  profit_margin: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
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
  tableName: 'items',
  timestamps: false,
  underscored: true,
});

export default Item;
