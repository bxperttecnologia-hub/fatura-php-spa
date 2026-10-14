import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Instruction = sequelize.define('Instruction', {
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
  title: {
    type: DataTypes.STRING(180),
    allowNull: false,
  },
  question: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  content: {
    type: DataTypes.TEXT,
    allowNull: false,
  },
  steps: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  tips: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  youtube_urls: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  keywords: {
    type: DataTypes.STRING(500),
    allowNull: true,
  },
  active: {
    type: DataTypes.BOOLEAN,
    allowNull: false,
    defaultValue: 1,
  },
  priority: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 0,
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
  tableName: 'instructions',
  timestamps: false,
  underscored: true,
});

export default Instruction;
