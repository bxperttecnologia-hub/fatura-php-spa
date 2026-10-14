import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Guide = sequelize.define('Guide', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  contact_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  vehicle_plate: {
    type: DataTypes.STRING(32),
    allowNull: true,
  },
  cargo_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  transport_reason: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  observations: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  retention: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  cargo_address: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  cargo_city: {
    type: DataTypes.STRING(120),
    allowNull: false,
  },
  cargo_po_box: {
    type: DataTypes.STRING(40),
    allowNull: true,
  },
  delivery_address: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  delivery_city: {
    type: DataTypes.STRING(120),
    allowNull: false,
  },
  delivery_po_box: {
    type: DataTypes.STRING(40),
    allowNull: true,
  },
  document_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  reference: {
    type: DataTypes.STRING(90),
    allowNull: true,
  },
  series: {
    type: DataTypes.STRING(32),
    allowNull: true,
  },
  currency: {
    type: DataTypes.STRING(8),
    allowNull: true,
    defaultValue: 'AOA',
  },
  total_sum: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  total_discount: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  subtotal_without_tax: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  total_tax: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  retention_value: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  final_total: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'guides',
  timestamps: false,
  underscored: true,
});

export default Guide;
