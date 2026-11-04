const getColumnType = async (queryInterface, table, column) => {
  const [rows] = await queryInterface.sequelize.query(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
    { replacements: [table, column] },
  );
  return rows[0]?.COLUMN_TYPE?.toLowerCase() ?? null;
};

module.exports = {
  async up(queryInterface) {
    const type = await getColumnType(queryInterface, 'invoice_items', 'quantity');
    if (!type) {
      throw new Error('The invoice_items.quantity column does not exist.');
    }
    if (!type.startsWith('double')) {
      await queryInterface.sequelize.query(
        'ALTER TABLE `invoice_items` MODIFY COLUMN `quantity` DOUBLE NOT NULL',
      );
    }
  },

  async down(queryInterface) {
    const type = await getColumnType(queryInterface, 'invoice_items', 'quantity');
    if (!type || type.startsWith('int')) {
      return;
    }

    const [rows] = await queryInterface.sequelize.query(
      'SELECT 1 FROM `invoice_items` WHERE `quantity` <> TRUNCATE(`quantity`, 0) LIMIT 1',
    );
    if (rows.length > 0) {
      throw new Error('Cannot restore integer quantities while fractional invoice items exist.');
    }

    await queryInterface.sequelize.query(
      'ALTER TABLE `invoice_items` MODIFY COLUMN `quantity` INT(11) NOT NULL',
    );
  },
};
