import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const EvaluationTemplate = sequelize.define('EvaluationTemplate', {
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
}, {
  tableName: 'evaluation_templates',
  timestamps: false,
  underscored: true,
});

export default EvaluationTemplate;
