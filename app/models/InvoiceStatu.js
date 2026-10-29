import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const InvoiceStatu = sequelize.define('InvoiceStatu', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  name: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  color: {
    type: DataTypes.STRING(7),
    allowNull: false,
  },
  text_color: {
    type: DataTypes.STRING(7),
    allowNull: false,
  },
}, {
  tableName: 'invoice_status',
  timestamps: false,
  underscored: true,
});

export default InvoiceStatu;
