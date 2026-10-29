import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const EvaluationCycle = sequelize.define('EvaluationCycle', {
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
    type: DataTypes.STRING(150),
    allowNull: false,
  },
  period_start: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  period_end: {
    type: DataTypes.DATEONLY,
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('aberto', 'fechado', 'aberto'),
    allowNull: true,
    defaultValue: 'aberto',
  },
}, {
  tableName: 'evaluation_cycles',
  timestamps: false,
  underscored: true,
});

export default EvaluationCycle;
