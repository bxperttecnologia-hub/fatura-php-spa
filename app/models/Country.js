import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Country = sequelize.define('Country', {
  code: {
    type: DataTypes.STRING(10),
    allowNull: false,
  },
  phone: {
    type: DataTypes.STRING(10),
    allowNull: true,
  },
  iso: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  iso3: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  name: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  formal_name: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
}, {
  tableName: 'countries',
  timestamps: false,
  underscored: true,
});

export default Country;
