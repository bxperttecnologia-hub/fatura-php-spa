import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const BusinessHealthScore = sequelize.define('BusinessHealthScore', {
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
  snapshot_date: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  overall_score: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  financeiro_score: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  vendas_score: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  clientes_score: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  stock_score: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  rh_score: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  explanation: {
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
  tableName: 'business_health_scores',
  timestamps: false,
  underscored: true,
});

export default BusinessHealthScore;
