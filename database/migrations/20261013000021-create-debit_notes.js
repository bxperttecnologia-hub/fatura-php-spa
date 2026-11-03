module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query(`CREATE TABLE \`debit_notes\` (
  \`id\` int(10) unsigned NOT NULL AUTO_INCREMENT,
  \`invoice_id\` int(11) NOT NULL,
  \`contact_id\` int(11) NOT NULL,
  \`company_id\` int(11) NOT NULL,
  \`serie\` varchar(10) NOT NULL DEFAULT 'ND',
  \`number\` int(10) unsigned NOT NULL,
  \`issue_date\` date NOT NULL,
  \`reason\` text NOT NULL,
  \`currency\` varchar(10) NOT NULL,
  \`subtotal_without_tax\` double NOT NULL DEFAULT 0.00,
  \`total_tax\` double NOT NULL DEFAULT 0.00,
  \`final_total\` double NOT NULL DEFAULT 0.00,
  \`user_id\` int(11) DEFAULT NULL,
  \`created_at\` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (\`id\`),
  UNIQUE KEY \`uq_nd_company_serie_number\` (\`company_id\`,\`serie\`,\`number\`),
  KEY \`idx_nd_invoice\` (\`invoice_id\`),
  KEY \`idx_nd_company\` (\`company_id\`),
  CONSTRAINT \`fk_nd_invoice\` FOREIGN KEY (\`invoice_id\`) REFERENCES \`invoices\` (\`id\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`);
  },

  async down(queryInterface) {
    await queryInterface.dropTable('debit_notes');
  },
};
