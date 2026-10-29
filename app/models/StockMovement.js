import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const StockMovement = sequelize.define('StockMovement', {
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
  stock_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  item_id: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  movement_type: {
    type: DataTypes.ENUM('entrada', 'saida', 'transferencia', 'ajuste', 'devolucao', 'inventario'),
    allowNull: false,
  },
  quantity: {
    type: DataTypes.INTEGER,
    allowNull: false,
  },
  unit_price: {
    type: DataTypes.DECIMAL,
    allowNull: true,
  },
  source_stock_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  destination_stock_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  reference_type: {
    type: DataTypes.STRING(100),
    allowNull: true,
  },
  reference_id: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  notes: {
    type: DataTypes.TEXT,
    allowNull: true,
  },
  created_by: {
    type: DataTypes.INTEGER,
    allowNull: true,
  },
  created_at: {
    type: DataTypes.DATE,
    allowNull: true,
    defaultValue: Sequelize.NOW,
  },
}, {
  tableName: 'stock_movements',
  timestamps: false,
  underscored: true,
});

export default StockMovement;
