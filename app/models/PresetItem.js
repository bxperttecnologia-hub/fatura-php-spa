import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const PresetItem = sequelize.define('PresetItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
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
    allowNull: true,
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
  suggested_price: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  tax: {
    type: DataTypes.ENUM('0', '5', '7', '14', '14'),
    allowNull: true,
    defaultValue: '14',
  },
}, {
  tableName: 'preset_items',
  timestamps: false,
  underscored: true,
});

export default PresetItem;
