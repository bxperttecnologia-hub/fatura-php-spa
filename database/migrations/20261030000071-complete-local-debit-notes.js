const columnExists = async (queryInterface, table, column) => {
  const [rows] = await queryInterface.sequelize.query(
    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
    { replacements: [table, column] },
  );
  return rows.length > 0;
};

const getColumnType = async (queryInterface, table, column) => {
  const [rows] = await queryInterface.sequelize.query(
    'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
    { replacements: [table, column] },
  );
  return rows[0]?.COLUMN_TYPE?.toLowerCase() ?? null;
};

const indexExists = async (queryInterface, table, index) => {
  const [rows] = await queryInterface.sequelize.query(
    'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
    { replacements: [table, index] },
  );
  return rows.length > 0;
};

const ensureColumn = async (queryInterface, table, column, definition, expectedType) => {
  if (!(await columnExists(queryInterface, table, column))) {
    await queryInterface.sequelize.query(
      `ALTER TABLE \`${table}\` ADD COLUMN \`${column}\` ${definition}`,
    );
  } else if (expectedType && (await getColumnType(queryInterface, table, column)) !== expectedType) {
    await queryInterface.sequelize.query(
      `ALTER TABLE \`${table}\` MODIFY COLUMN \`${column}\` ${definition}`,
    );
  }
};

module.exports = {
  async up(queryInterface) {
    await ensureColumn(queryInterface, 'debit_notes', 'total_sum',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `reason`', 'double');
    await ensureColumn(queryInterface, 'debit_notes', 'total_discount',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `subtotal_without_tax`', 'double');
    await ensureColumn(queryInterface, 'debit_notes', 'subtotal_without_tax',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `currency`', 'double');
    await ensureColumn(queryInterface, 'debit_notes', 'total_tax',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `total_discount`', 'double');
    await ensureColumn(queryInterface, 'debit_notes', 'final_total',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `total_tax`', 'double');
    await ensureColumn(queryInterface, 'debit_notes', 'hash',
      'VARCHAR(200) DEFAULT NULL AFTER `user_id`');
    await ensureColumn(queryInterface, 'debit_notes', 'idempotency_key',
      'CHAR(36) DEFAULT NULL AFTER `hash`');
    await ensureColumn(queryInterface, 'debit_notes', 'agt_status',
      "VARCHAR(12) NOT NULL DEFAULT 'blocked' AFTER `idempotency_key`");
    if (!(await indexExists(queryInterface, 'debit_notes', 'uq_nd_company_idempotency'))) {
      await queryInterface.sequelize.query(
        'ALTER TABLE `debit_notes` ADD UNIQUE KEY `uq_nd_company_idempotency` (`company_id`, `idempotency_key`)',
      );
    }
    await ensureColumn(queryInterface, 'debit_note_items', 'quantity',
      'DOUBLE NOT NULL DEFAULT 1.000 AFTER `description`', 'double');
    await ensureColumn(queryInterface, 'debit_note_items', 'unit_price',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `quantity`', 'double');
    await ensureColumn(queryInterface, 'debit_note_items', 'discount',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `unit_price`', 'double');
    await ensureColumn(queryInterface, 'debit_note_items', 'tax',
      'DOUBLE NOT NULL DEFAULT 0.00 AFTER `discount`', 'double');
  },

  async down(queryInterface) {
    if (await indexExists(queryInterface, 'debit_notes', 'uq_nd_company_idempotency')) {
      await queryInterface.sequelize.query(
        'ALTER TABLE `debit_notes` DROP INDEX `uq_nd_company_idempotency`',
      );
    }
    for (const column of ['agt_status', 'idempotency_key', 'hash', 'total_sum']) {
      if (await columnExists(queryInterface, 'debit_notes', column)) {
        await queryInterface.sequelize.query(
          `ALTER TABLE \`debit_notes\` DROP COLUMN \`${column}\``,
        );
      }
    }
  },
};
