import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Stock = sequelize.define('Stock', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  name: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  description: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
  color: {
    type: DataTypes.STRING(20),
    allowNull: true,
  },
  icon: {
    type: DataTypes.STRING(50),
    allowNull: true,
    defaultValue: 'box',
  },
  location_type: {
    type: DataTypes.ENUM('interna', 'externa', 'interna'),
    allowNull: true,
    defaultValue: 'interna',
  },
  location_detail: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  display_name: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  address: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  address_number: {
    type: DataTypes.STRING(100),
    allowNull: false,
  },
  neighborhood: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  city: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  county: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  state: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  state_district: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  region: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  country: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  continent: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  iso_region_code: {
    type: DataTypes.STRING(20),
    allowNull: true,
  },
  osm_type: {
    type: DataTypes.STRING(20),
    allowNull: true,
  },
  osm_id: {
    type: DataTypes.BIGINT,
    allowNull: true,
  },
  boundingbox: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  place_class: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  place_type: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  zip_code: {
    type: DataTypes.STRING(50),
    allowNull: false,
  },
  latitude: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  longitude: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  updated_by: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
}, {
  tableName: 'stocks',
  timestamps: false,
  underscored: true,
});

export default Stock;
