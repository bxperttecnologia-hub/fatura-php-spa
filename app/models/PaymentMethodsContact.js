import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const PaymentMethodsContact = sequelize.define('PaymentMethodsContact', {
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
}, {
  tableName: 'payment_methods_contacts',
  timestamps: false,
  underscored: true,
});

export default PaymentMethodsContact;
