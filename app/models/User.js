import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const User = sequelize.define('User', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  username: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  name: {
    type: DataTypes.STRING(1000),
    allowNull: false,
  },
  phone: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  email: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  password: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  gender: {
    type: DataTypes.ENUM('Masculino', 'Feminino', 'Outro', 'prefer_not_to_say'),
    allowNull: false,
  },
  identification_type: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  identification: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  address: {
    type: DataTypes.TEXT,
    allowNull: false,
  },
  address_number: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  address_neighborhood: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  address_district: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  zip_code: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  country: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  city: {
    type: DataTypes.STRING(200),
    allowNull: true,
  },
  blocked: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
  },
  image: {
    type: DataTypes.STRING(255),
    allowNull: false,
    defaultValue: 'default.png',
  },
  lang: {
    type: DataTypes.STRING(255),
    allowNull: false,
    defaultValue: 'angola',
  },
  currency: {
    type: DataTypes.STRING(100),
    allowNull: false,
  },
  is_active: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
  },
  adm: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 0,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
  must_change_password: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 0,
  },
  phone_verified_at: {
    type: DataTypes.DATE,
    allowNull: true,
  },
}, {
  tableName: 'users',
  timestamps: false,
  underscored: true,
});

export default User;
