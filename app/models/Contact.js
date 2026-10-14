import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Contact = sequelize.define('Contact', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  type: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  name: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  contributor: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  address: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  website: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  country: {
    type: DataTypes.STRING(100),
    allowNull: true,
    defaultValue: 'Angola',
  },
  city: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  email: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  telephone_ddi: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  telephone: {
    type: DataTypes.STRING(60),
    allowNull: true,
  },
  cellphone_ddi: {
    type: DataTypes.STRING(10),
    allowNull: true,
  },
  cellphone: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  po_box: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  fax: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  pref_name: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  pref_email: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  pref_telephone_ddi: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  pref_telephone: {
    type: DataTypes.STRING(60),
    allowNull: true,
  },
  pref_cellphone_ddi: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  pref_cellphone: {
    type: DataTypes.STRING(60),
    allowNull: true,
  },
  numberCopys: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  observations: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  due_date: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  language: {
    type: DataTypes.STRING(3),
    allowNull: true,
  },
  payment_method: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  currency: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  is_active: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 1,
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
  tableName: 'contact',
  timestamps: false,
  underscored: true,
});

export default Contact;
