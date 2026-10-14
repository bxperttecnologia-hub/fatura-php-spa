import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const ProformaSequence = sequelize.define('ProformaSequence', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    primaryKey: true,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  year: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  last_number: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 0,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'proforma_sequences',
  timestamps: false,
  underscored: true,
});

export default ProformaSequence;
