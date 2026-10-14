import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const MonthlyReport = sequelize.define('MonthlyReport', {
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
  year: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  month: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  total_faturas: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  receita_total: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  receita_paga: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  valor_em_atraso: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  top_clients: {
    type: DataTypes.JSON,
    allowNull: true,
  },
  suggestions: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  ai_provider: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: false,
  },
}, {
  tableName: 'monthly_reports',
  timestamps: false,
  underscored: true,
});

export default MonthlyReport;
