import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const EvaluationAnswer = sequelize.define('EvaluationAnswer', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  evaluation_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  criterion_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  score: {
    type: DataTypes.DECIMAL,
    allowNull: false,
  },
  comment: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
}, {
  tableName: 'evaluation_answers',
  timestamps: false,
  underscored: true,
});

export default EvaluationAnswer;
