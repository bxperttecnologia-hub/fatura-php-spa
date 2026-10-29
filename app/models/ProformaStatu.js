import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const ProformaStatu = sequelize.define('ProformaStatu', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    primaryKey: true,
  },
  name: {
    type: DataTypes.STRING(100),
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
  tableName: 'proforma_status',
  timestamps: false,
  underscored: true,
});

export default ProformaStatu;
