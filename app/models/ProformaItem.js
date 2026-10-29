import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const ProformaItem = sequelize.define('ProformaItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  proforma_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  quantity: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  unit_price: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  tax: {
    type: DataTypes.DOUBLE,
    allowNull: true,
    defaultValue: 0,
  },
  discount: {
    type: DataTypes.DOUBLE,
    allowNull: true,
    defaultValue: 0,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'proforma_items',
  timestamps: false,
  underscored: true,
});

export default ProformaItem;
