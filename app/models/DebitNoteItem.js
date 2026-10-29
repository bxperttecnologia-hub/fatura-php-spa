import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const DebitNoteItem = sequelize.define('DebitNoteItem', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  debit_note_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  description: {
    type: DataTypes.STRING(500),
    allowNull: false,
  },
  quantity: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 1.0,
  },
  unit_price: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
  tax: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 0.0,
  },
}, {
  tableName: 'debit_note_items',
  timestamps: false,
  underscored: true,
});

export default DebitNoteItem;
