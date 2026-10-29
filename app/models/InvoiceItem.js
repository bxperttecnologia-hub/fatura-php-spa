import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const InvoiceItem = sequelize.define('InvoiceItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  invoice_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  quantity: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  unit_price: {
    type: DataTypes.DOUBLE,
    allowNull: false,
  },
  tax: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  discount: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
}, {
  tableName: 'invoice_items',
  timestamps: false,
  underscored: true,
});

export default InvoiceItem;
