import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Evaluation = sequelize.define('Evaluation', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  cycle_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  employee_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  evaluator_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  template_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('pendente', 'em_curso', 'concluida', 'pendente'),
    allowNull: true,
    defaultValue: 'pendente',
  },
  final_score: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'evaluations',
  timestamps: false,
  underscored: true,
});

export default Evaluation;
