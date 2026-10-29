import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const DeliveryNoteItem = sequelize.define('DeliveryNoteItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  delivery_note_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  invoice_item_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  description: {
    type: DataTypes.STRING(500),
    allowNull: true,
  },
  quantity: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
}, {
  tableName: 'delivery_note_items',
  timestamps: false,
  underscored: true,
});

export default DeliveryNoteItem;
