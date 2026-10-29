import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const BusinessInsight = sequelize.define('BusinessInsight', {
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
  category: {
    type: DataTypes.ENUM('oportunidade', 'risco', 'financeiro', 'rh', 'comercial', 'stock'),
    allowNull: false,
  },
  severity: {
    type: DataTypes.ENUM('critico', 'atencao', 'aviso', 'info', 'oportunidade', 'info'),
    allowNull: false,
    defaultValue: 'info',
  },
  entity_type: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  entity_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  title: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  evidence: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  recommendation: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  estimated_impact: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  dedupe_key: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('aberto', 'resolvido', 'ignorado', 'aberto'),
    allowNull: false,
    defaultValue: 'aberto',
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
  tableName: 'business_insights',
  timestamps: false,
  underscored: true,
});

export default BusinessInsight;
