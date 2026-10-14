import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const CollectionSchedule = sequelize.define('CollectionSchedule', {
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
  invoice_ids: {
    type: DataTypes.JSON,
    allowNull: false,
  },
  channels: {
    type: DataTypes.JSON,
    allowNull: false,
  },
  subject: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  message: {
    type: DataTypes.TEXT,
    allowNull: false,
  },
  scheduled_at: {
    type: DataTypes.DATE,
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('scheduled', 'processing', 'sent', 'partial', 'failed', 'scheduled'),
    allowNull: true,
    defaultValue: 'scheduled',
  },
  result: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  createdAt: {
    type: DataTypes.DATE,
    allowNull: false,
  },
  updatedAt: {
    type: DataTypes.DATE,
    allowNull: false,
  },
}, {
  tableName: 'collection_schedules',
  timestamps: false,
  underscored: true,
});

export default CollectionSchedule;
