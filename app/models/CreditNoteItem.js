import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CreditNoteItem = sequelize.define('CreditNoteItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  credit_note_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  description: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  quantity: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
  },
  unit_price: {
    type: DataTypes.DOUBLE,
    allowNull: false,
    defaultValue: 0,
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
  tableName: 'credit_note_items',
  timestamps: false,
  underscored: true,
});

export default CreditNoteItem;
