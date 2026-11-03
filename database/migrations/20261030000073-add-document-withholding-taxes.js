module.exports = {
  async up(queryInterface, Sequelize) {
    for (const table of ['invoices', 'proformas']) {
      await queryInterface.addColumn(table, 'tax_withholding_type', {
        type: Sequelize.STRING(32),
        allowNull: false,
        defaultValue: 'none',
      });
      await queryInterface.addColumn(table, 'tax_withholding_rate', {
        type: Sequelize.DECIMAL(7, 4),
        allowNull: false,
        defaultValue: 0,
      });
      await queryInterface.addColumn(table, 'tax_withholding_base', {
        type: Sequelize.DECIMAL(15, 2),
        allowNull: false,
        defaultValue: 0,
      });
      await queryInterface.addColumn(table, 'tax_withholding_amount', {
        type: Sequelize.DECIMAL(15, 2),
        allowNull: false,
        defaultValue: 0,
      });
      await queryInterface.addColumn(table, 'tax_withholding_description', {
        type: Sequelize.STRING(160),
        allowNull: true,
      });
      await queryInterface.addColumn(table, 'tax_withholding_confirmed', {
        type: Sequelize.BOOLEAN,
        allowNull: false,
        defaultValue: false,
      });
      await queryInterface.addColumn(table, 'gross_total', {
        type: Sequelize.DECIMAL(15, 2),
        allowNull: false,
        defaultValue: 0,
      });
      await queryInterface.addColumn(table, 'net_payable', {
        type: Sequelize.DECIMAL(15, 2),
        allowNull: false,
        defaultValue: 0,
      });
    }

    await queryInterface.addColumn('items', 'tax_classification', {
      type: Sequelize.ENUM('standard', 'withholding_service', 'rental_income'),
      allowNull: false,
      defaultValue: 'standard',
    });

    await queryInterface.createTable('company_document_tax_rates', {
      id: { type: Sequelize.INTEGER, primaryKey: true, autoIncrement: true, allowNull: false },
      company_id: { type: Sequelize.INTEGER, allowNull: false },
      tax_type: { type: Sequelize.STRING(32), allowNull: false },
      rate: { type: Sequelize.DECIMAL(7, 4), allowNull: false },
      base_type: { type: Sequelize.STRING(32), allowNull: false },
      enabled: { type: Sequelize.BOOLEAN, allowNull: false, defaultValue: false },
      effective_from: { type: Sequelize.DATEONLY, allowNull: false },
      effective_to: { type: Sequelize.DATEONLY, allowNull: true },
      legal_validation_note: { type: Sequelize.STRING(255), allowNull: true },
      created_by: { type: Sequelize.INTEGER, allowNull: false },
      created_at: { type: Sequelize.DATE, allowNull: false, defaultValue: Sequelize.NOW },
    });
    await queryInterface.addIndex('company_document_tax_rates', ['company_id', 'tax_type', 'effective_from'], {
      unique: true,
      name: 'company_tax_rate_period_unique',
    });
    await queryInterface.addIndex('company_document_tax_rates', ['company_id', 'tax_type', 'effective_to'], {
      name: 'company_tax_rate_lookup',
    });
  },

  async down(queryInterface) {
    await queryInterface.dropTable('company_document_tax_rates');
    await queryInterface.removeColumn('items', 'tax_classification');
    for (const table of ['invoices', 'proformas']) {
      for (const column of [
        'tax_withholding_type',
        'tax_withholding_rate',
        'tax_withholding_base',
        'tax_withholding_amount',
        'tax_withholding_description',
        'tax_withholding_confirmed',
        'gross_total',
        'net_payable',
      ]) {
        await queryInterface.removeColumn(table, column);
      }
    }
  },
};
