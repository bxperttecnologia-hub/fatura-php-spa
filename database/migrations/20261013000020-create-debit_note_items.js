module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query(`CREATE TABLE \`debit_note_items\` (
  \`id\` int(10) unsigned NOT NULL AUTO_INCREMENT,
  \`debit_note_id\` int(10) unsigned NOT NULL,
  \`description\` varchar(500) NOT NULL,
  \`quantity\` double NOT NULL DEFAULT 1.000,
  \`unit_price\` double NOT NULL DEFAULT 0.00,
  \`tax\` double NOT NULL DEFAULT 0.00,
  PRIMARY KEY (\`id\`),
  KEY \`idx_ndi_note\` (\`debit_note_id\`),
  CONSTRAINT \`fk_ndi_note\` FOREIGN KEY (\`debit_note_id\`) REFERENCES \`debit_notes\` (\`id\`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`);
  },

  async down(queryInterface) {
    await queryInterface.dropTable('debit_note_items');
  },
};
