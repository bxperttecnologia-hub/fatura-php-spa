import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Payroll = sequelize.define('Payroll', {
  id: {
    type: DataTypes.INTEGER,
    allowNull: false,
    autoIncrement: true,
    primaryKey: true,
  },
  employee_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  company_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  reference_month: {
    type: DataTypes.STRING(7),
    allowNull: false,
  },
  base_salary: {
    type: DataTypes.DECIMAL,
    allowNull: false,
  },
  bonuses: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  food_allowance: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  transport_allowance: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  vacation_subsidy_pct: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  thirteenth_subsidy_pct: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 0,
  },
  commissions: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  sales: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  discounts: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  total_discounts: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  inss_value: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  inss_employer_value: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  irt_value: {
    type: DataTypes.DECIMAL,
    allowNull: true,
    defaultValue: 0.0,
  },
  net_salary: {
    type: DataTypes.DECIMAL,
    allowNull: false,
  },
  payment_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  status: {
    type: DataTypes.ENUM('Pago', 'Pendente', 'Pendente'),
    allowNull: true,
    defaultValue: 'Pendente',
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'payroll',
  timestamps: false,
  underscored: true,
});

export default Payroll;
