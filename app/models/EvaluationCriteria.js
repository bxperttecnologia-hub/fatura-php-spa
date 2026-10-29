import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const EvaluationCriteria = sequelize.define('EvaluationCriteria', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  template_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  label: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  weight: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 1.0,
  },
}, {
  tableName: 'evaluation_criteria',
  timestamps: false,
  underscored: true,
});

export default EvaluationCriteria;
