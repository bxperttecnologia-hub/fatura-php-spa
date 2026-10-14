import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Company = sequelize.define('Company', {
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
  acronym: {
    type: DataTypes.STRING(6),
    allowNull: true,
  },
  registration_number: {
    type: DataTypes.STRING(50),
    allowNull: false,
  },
  phone_ddi: {
    type: DataTypes.STRING(5),
    allowNull: false,
  },
  phone: {
    type: DataTypes.STRING(20),
    allowNull: true,
  },
  email: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  website: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  address: {
    type: DataTypes.STRING(255),
    allowNull: true,
    defaultValue: 'Angola',
  },
  city: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  country: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  zip_code: {
    type: DataTypes.STRING(20),
    allowNull: true,
  },
  logo_url: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  plan_code: {
    type: DataTypes.STRING(40),
    allowNull: true,
  },
  plan_started_at: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  plan_expires_at: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  plan_status: {
    type: DataTypes.ENUM('active', 'expired', 'active'),
    allowNull: true,
    defaultValue: 'active',
  },
  payroll_open_day: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 20,
  },
  payroll_close_day: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 28,
  },
  currency: {
    type: DataTypes.STRING(3),
    allowNull: false,
    defaultValue: 'AOA',
  },
  vat_regime: {
    type: DataTypes.ENUM('geral', 'simplificado', 'isento', 'geral'),
    allowNull: true,
    defaultValue: 'geral',
  },
  default_vat_rate: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 14.0,
  },
  vat_included_prices: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  withholding_enabled: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  withholding_rate: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  vat_retention: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  goods_services: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  bank_details: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  bank_name: {
    type: DataTypes.STRING(120),
    allowNull: true,
  },
  bank_name_optional: {
    type: DataTypes.STRING(120),
    allowNull: true,
  },
  iban: {
    type: DataTypes.STRING(64),
    allowNull: true,
  },
  iban_optional: {
    type: DataTypes.STRING(64),
    allowNull: true,
  },
  is_active: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 1,
  },
  blocked: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 0,
  },
  invoice_impression: {
    type: DataTypes.ENUM('A4', 'A5', 'Recibo', 'A4'),
    allowNull: false,
    defaultValue: 'A4',
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'companies',
  timestamps: false,
  underscored: true,
});

export default Company;
