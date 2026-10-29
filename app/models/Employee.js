import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Employee = sequelize.define('Employee', {
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
  bi: {
    type: DataTypes.STRING(50),
    allowNull: false,
  },
  position: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  email: {
    type: DataTypes.STRING(150),
    allowNull: true,
  },
  phone_ddi: {
    type: DataTypes.STRING(5),
    allowNull: true,
  },
  phone: {
    type: DataTypes.STRING(30),
    allowNull: true,
  },
  salary_base: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  iban: {
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  status: {
    type: DataTypes.ENUM('ativo', 'inativo', 'ativo'),
    allowNull: true,
    defaultValue: 'ativo',
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
  document_type: {
    type: DataTypes.STRING(30),
    allowNull: true,
  },
  birth_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  marital_status: {
    type: DataTypes.STRING(30),
    allowNull: true,
  },
  academic_level: {
    type: DataTypes.STRING(30),
    allowNull: true,
  },
  photo_url: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  doc1_url: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  doc2_url: {
    type: DataTypes.STRING(255),
    allowNull: true,
  },
  contract_type: {
    type: DataTypes.STRING(50),
    allowNull: true,
  },
  contract_end_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  admission_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  end_date: {
    type: DataTypes.DATEONLY,
    allowNull: true,
  },
  termination_type: {
    type: DataTypes.STRING(60),
    allowNull: true,
  },
  termination_amount: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  termination_notes: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  position_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  department_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  manager_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
}, {
  tableName: 'employees',
  timestamps: false,
  underscored: true,
});

export default Employee;
