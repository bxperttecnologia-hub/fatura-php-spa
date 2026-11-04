const columnExists = async (queryInterface) => {
  const [rows] = await queryInterface.sequelize.query(
    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credit_note_items' AND COLUMN_NAME = 'retention_rate' LIMIT 1",
  );
  return rows.length > 0;
};

module.exports = {
  async up(queryInterface) {
    if (!(await columnExists(queryInterface))) {
      await queryInterface.sequelize.query(
        'ALTER TABLE `credit_note_items` ADD COLUMN `retention_rate` DECIMAL(8,4) NULL DEFAULT NULL AFTER `discount`',
      );
    }
  },

  async down(queryInterface) {
    if (await columnExists(queryInterface)) {
      await queryInterface.sequelize.query(
        'ALTER TABLE `credit_note_items` DROP COLUMN `retention_rate`',
      );
    }
  },
};
