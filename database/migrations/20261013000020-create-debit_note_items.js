module.exports = {
  async up(queryInterface) {
    await queryInterface.sequelize.query('CREATE TABLE `debit_note_items` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `debit_note_id` int(10) unsigned NOT NULL,\n  `description` varchar(500) NOT NULL,\n  `quantity` decimal(14,3) NOT NULL DEFAULT 1.000,\n  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,\n  `tax` decimal(5,2) NOT NULL DEFAULT 0.00,\n  PRIMARY KEY (`id`),\n  KEY `idx_ndi_note` (`debit_note_id`),\n  CONSTRAINT `fk_ndi_note` FOREIGN KEY (`debit_note_id`) REFERENCES `debit_notes` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  },

  async down(queryInterface) {
    await queryInterface.dropTable('debit_note_items');
  },
};
