import Sequelize, { DataTypes } from 'sequelize';
import { sequelize } from '../../config/database.js';

export const Product = sequelize.define('Product', {
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
    type: DataTypes.STRING(255),
    allowNull: false,
  },
  current_stock: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  min_stock: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  cost_price: {
    type: DataTypes.DOUBLE,
    allowNull: true,
  },
  active: {
    type: DataTypes.BOOLEAN,
    allowNull: true,
    defaultValue: 1,
  },
}, {
  tableName: 'products',
  timestamps: false,
  underscored: true,
});

export default Product;
