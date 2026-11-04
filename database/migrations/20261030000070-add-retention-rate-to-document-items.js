const columnExists = async (queryInterface, table, column) => {
  const [rows] = await queryInterface.sequelize.query(
    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
    { replacements: [table, column] },
  );
  return rows.length > 0;
};

const addRetentionRateIfMissing = async (queryInterface, table) => {
  if (!(await columnExists(queryInterface, table, 'retention_rate'))) {
    await queryInterface.sequelize.query(
      `ALTER TABLE \`${table}\` ADD COLUMN \`retention_rate\` DECIMAL(8,4) NULL DEFAULT NULL AFTER \`discount\``,
    );
  }
};

module.exports = {
  async up(queryInterface) {
    await addRetentionRateIfMissing(queryInterface, 'invoice_items');
    await addRetentionRateIfMissing(queryInterface, 'proforma_items');
  },

  async down(queryInterface) {
    for (const table of ['proforma_items', 'invoice_items']) {
      if (await columnExists(queryInterface, table, 'retention_rate')) {
        await queryInterface.sequelize.query(
          `ALTER TABLE \`${table}\` DROP COLUMN \`retention_rate\``,
        );
      }
    }
  },
};
