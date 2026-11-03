module.exports = {
  async up(queryInterface, Sequelize) {
    await queryInterface.addColumn('credit_notes', 'tax_withholding_type', {
      type: Sequelize.STRING(32),
      allowNull: false,
      defaultValue: 'none',
    });
    await queryInterface.addColumn('credit_notes', 'tax_withholding_rate', {
      type: Sequelize.DECIMAL(7, 4),
      allowNull: false,
      defaultValue: 0,
    });
    await queryInterface.addColumn('credit_notes', 'tax_withholding_base', {
      type: Sequelize.DECIMAL(15, 2),
      allowNull: false,
      defaultValue: 0,
    });
    await queryInterface.addColumn('credit_notes', 'tax_withholding_amount', {
      type: Sequelize.DECIMAL(15, 2),
      allowNull: false,
      defaultValue: 0,
    });
    await queryInterface.addColumn('credit_notes', 'tax_withholding_description', {
      type: Sequelize.STRING(160),
      allowNull: true,
    });
    await queryInterface.addColumn('credit_notes', 'tax_withholding_confirmed', {
      type: Sequelize.BOOLEAN,
      allowNull: false,
      defaultValue: false,
    });
    await queryInterface.addColumn('credit_notes', 'gross_total', {
      type: Sequelize.DECIMAL(15, 2),
      allowNull: false,
      defaultValue: 0,
    });
  },

  async down(queryInterface) {
    for (const column of [
      'tax_withholding_type',
      'tax_withholding_rate',
      'tax_withholding_base',
      'tax_withholding_amount',
      'tax_withholding_description',
      'tax_withholding_confirmed',
      'gross_total',
    ]) {
      await queryInterface.removeColumn('credit_notes', column);
    }
  },
};
