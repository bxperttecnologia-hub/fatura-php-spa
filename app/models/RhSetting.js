import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const RhSetting = sequelize.define('RhSetting', {
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    primaryKey: true,
  },
  salario_minimo_nacional: {
    type: DataTypes.DECIMAL,
    allowNull: false,
    defaultValue: 70000.0,
  },
  aviso_previo_demissao_dias: {
    type: DataTypes.INTEGER,
    allowNull: false,
    defaultValue: 30,
  },
  updated_at: {
    type: DataTypes.DATE,
    allowNull: false,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'rh_settings',
  timestamps: false,
  underscored: true,
});

export default RhSetting;
